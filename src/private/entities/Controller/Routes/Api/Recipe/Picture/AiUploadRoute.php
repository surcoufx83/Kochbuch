<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Recipe\Picture;

use Exception;
use Orhanerday\OpenAi\OpenAi;
use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;
use Surcouf\Cookbook\Recipe\Recipe;
use Surcouf\Cookbook\Response\AiRecipeScanResponse;
use Surcouf\Cookbook\Response\AiResponse;

if (!defined('CORE2'))
  exit;

class AiUploadRoute extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;
    $recipe = Recipe::load(intval($Controller->Dispatcher()->getFromMatches('id')));
    $recipeId = is_null($recipe) ? '' : $recipe->getId();

    if (count($_FILES) === 0) {
      $response = [
        'errorCode' => 2,
        'recipe' => null,
        'userError' => false,
      ];
      $Controller->e304_NotModified(__CLASS__, __METHOD__, __LINE__, 'Files array is empty', [$recipeId, $_FILES]);
    }

    if (!$Controller->aiAvailable()) {
      $response = [
        'errorCode' => 9,
        'recipe' => null,
        'userError' => false,
      ];
      $Controller->e304_NotModified(__CLASS__, __METHOD__, __LINE__, 'API Key is missing in config', [$recipeId]);
    }

    // Check any picture for correct mime type
    foreach ($_FILES as $key => $value) {
      if ($value['error'] > UPLOAD_ERR_OK) {
        $response = [
          'errorCode' => 90 + $value['error'],
          'recipe' => null,
          'userError' => $value['error'] == UPLOAD_ERR_PARTIAL || $value['error'] == UPLOAD_ERR_NO_FILE || $value['error'] == UPLOAD_ERR_INI_SIZE,
        ];
        $Controller->e400_BadRequest(__CLASS__, __METHOD__, __LINE__, 'Error flag set in uploaded file data', [$recipeId, $_FILES]);
      }

      if (!$Controller->isUploadAllowed($value['type'])) {
        $response = [
          'errorCode' => count($_FILES) === 1 ? 3 : 4,
          'recipe' => null,
          'userError' => false,
        ];
        $Controller->e400_BadRequest(__CLASS__, __METHOD__, __LINE__, 'Filetype ' . $value['type'] . ' for picture ' . $key . ' not allowed', [$recipeId, $_FILES]);
      }

      $tempname = $key . $recipeId . $value['name'];
      $temppath = sprintf($Controller->Config()->System('ChatGpt', 'PublicFolder'), $tempname);
      $tempuri = sprintf($Controller->Config()->System('ChatGpt', 'PublicUri'), $tempname);

      if (!@copy($value['tmp_name'], $temppath)) {
        if ($value['error'] > UPLOAD_ERR_OK) {
          $response = [
            'errorCode' => 6,
            'recipe' => null,
            'userError' => false,
          ];
          $Controller->e400_BadRequest(__CLASS__, __METHOD__, __LINE__, 'Unable to move to picture folder', [$recipeId, $_FILES]);
        }
      }

      $messages = [
        [
          'role' => 'system',
          'content' => 'Provide your answer as a RFC8259 compliant structure, do not include any other explanations. Use German text for the recipe data. '
            . 'The JSON structure may contain a name and a summary property which briefly describes the recipe. '
            . 'The instructions for preparation are contained in a `preparation` array. '
            . 'For each preparation step, there are the properties `ingredients` and `instructions`. The second is a simple string. '
            . '`ingredients` is also an array and contains one entry per ingredient. The entries are each objects with the properties `quantity`, `unit`, `name`. '
            . 'Other optional entries in the recipe are `servings` for the number of portions and `tips` for further tips. '
            . 'In case of any error, please use an error property to return an error message in German language.'
        ],
        [
          'role' => 'user',
          'content' => [
            [
              'type' => 'text',
              'text' => 'Please check the attached picture. Does it contain a recipe for cooking? If so, please tell me the name of the recipe and provide instructions on how to prepare it.',
            ],
            [
              'type' => 'image_url',
              'image_url' => $tempuri,
            ],
          ]
        ],
      ];

      try {
        $chat = $Controller->ai($messages);
      } catch (Exception $e) {
        $response = [
          'errorCode' => $e->getCode(),
          'errorMessage' => $e->getMessage(),
          'recipe' => null,
          'userError' => false,
        ];
        $Controller->e500_ServerError();
      }

      $responseObject = new AiResponse($chat);
      $repeat = 0;
      while (!$responseObject->success && $responseObject->errorMessage == 'Invalid image.' && $repeat < 2) {
        $repeat++;
        try {
          $chat = $Controller->ai($messages);
        } catch (Exception $e) {
          $response = [
            'errorCode' => $e->getCode(),
            'errorMessage' => $e->getMessage(),
            'recipe' => null,
            'userError' => false,
          ];
          $Controller->e500_ServerError();
        }
        $responseObject = new AiResponse($chat);
      }
      $airecipe = null;
      if ($responseObject->success && !is_null($responseObject->jsonPayload)) {
        $airecipe = Recipe::createFromAiScanner($responseObject->jsonPayload);
      }
      @unlink($temppath);

      $query = 'INSERT INTO recipe_ai_scanner(userid, request, response_raw, recipeid, tokens_in, token_out) VALUES(?, ?, ?, ?, ?, ?)';
      $userid = $Controller->User()->getId();
      $input = json_encode($messages);
      $recipeid = !is_null($airecipe) ? $airecipe->getId() : null;
      $tokensin = !is_null($responseObject->usage) ? $responseObject->usage->prompt_tokens : null;
      $tokensout = !is_null($responseObject->usage) ? $responseObject->usage->completion_tokens : null;
      $stmt = $Controller->prepare($query);
      if ($stmt) {
        $stmt->bind_param('issiii', $userid, $input, $chat, $recipeid, $tokensin, $tokensout);
        $stmt->execute();
      }

      if ($responseObject->success) {
        if (!is_null($airecipe)) {
          $airecipe->createPicture($response, $value);
          $response = [
            'errorCode' => 0,
            'recipe' => $airecipe,
            'userError' => false,
          ];
          $Controller->e201_Created();
        }
      }
      $response = [
        'errorCode' => -1,
        'errorMessage' => $responseObject->errorMessage,
        'recipe' => null,
        'userError' => false,
      ];
      $Controller->e400_BadRequest();

      break;
    }

    $response = [
      'errorCode' => -1,
      'errorMessage' => 'Can\'t process the uploaded picture.',
      'recipe' => null,
      'userError' => false,
    ];
    $Controller->e500_ServerError();

  }

}