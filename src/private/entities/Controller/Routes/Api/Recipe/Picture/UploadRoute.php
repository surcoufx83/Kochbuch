<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Recipe\Picture;

use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;
use Surcouf\Cookbook\Recipe\Recipe;

if (!defined('CORE2'))
  exit;

class UploadRoute extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;
    $recipe = Recipe::load(intval($Controller->Dispatcher()->getFromMatches('id')));

    if (is_null($recipe)) {
      $response = [
        'errorCode' => 1,
        'recipe' => null,
        'userError' => false,
      ];
      $Controller->e404_NotFound(__CLASS__, __METHOD__, __LINE__, 'Recipe not found', [intval($Controller->Dispatcher()->getFromMatches('id'))]);
    }

    if (count($_FILES) === 0) {
      $response = [
        'errorCode' => 2,
        'recipe' => null,
        'userError' => false,
      ];
      $Controller->e304_NotModified(__CLASS__, __METHOD__, __LINE__, 'Files array is empty', [$recipe->getId(), $_FILES]);
    }

    // Check any picture for correct mime type
    foreach ($_FILES as $key => $value) {
      if ($value['error'] > UPLOAD_ERR_OK) {
        $response = [
          'errorCode' => 90 + $value['error'],
          'recipe' => null,
          'userError' => $value['error'] == UPLOAD_ERR_PARTIAL || $value['error'] == UPLOAD_ERR_NO_FILE || $value['error'] == UPLOAD_ERR_INI_SIZE,
        ];
        $Controller->e400_BadRequest(__CLASS__, __METHOD__, __LINE__, 'Error flag set in uploaded file data', [$recipe->getId(), $_FILES]);
      }

      if (!$Controller->isUploadAllowed($value['type'])) {
        $response = [
          'errorCode' => count($_FILES) === 1 ? 3 : 4,
          'recipe' => null,
          'userError' => false,
        ];
        $Controller->e400_BadRequest(__CLASS__, __METHOD__, __LINE__, 'Filetype ' . $value['type'] . ' for picture ' . $key . ' not allowed', [$recipe->getId(), $_FILES]);
      }
    }

    if (!$Controller->startTransaction()) {
      $response = [
        'errorCode' => 5,
        'recipe' => null,
        'userError' => false,
      ];
      $Controller->e500_ServerError(__CLASS__, __METHOD__, __LINE__, 'Unable to start transaction', $Controller->dberror2());
    }

    $recipe->loadComplete();

    // Copy file to target destination and add it to the database
    foreach ($_FILES as $key => $value) {
      if (!$recipe->createPicture($response, $value)) {
        $Controller->cancelTransaction();
        $Controller->e500_ServerError(__CLASS__, __METHOD__, __LINE__, 'Unable to create picture object and move uploaded file.');
      }
    }

    if (!$Controller->finishTransaction()) {
      $response = [
        'errorCode' => 6,
        'recipe' => null,
        'userError' => false,
      ];
      $Controller->e500_ServerError(__CLASS__, __METHOD__, __LINE__, 'Unable to start transaction', $Controller->dberror2());
    }

    $response = [
      'errorCode' => 0,
      'recipe' => $recipe,
      'userError' => false,
    ];

    $Controller->e202_Accepted();

  }

}