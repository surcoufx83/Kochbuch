<?php

namespace Surcouf\Cookbook\Cli\Cronjob;

use Exception;
use Surcouf\Cookbook\Cli\Cronjob\ICronjob;
use Surcouf\Cookbook\Recipe\Recipe;
use Surcouf\Cookbook\Response\AiResponse;

if (!defined('CORE2'))
    exit;

class TranslateRecipesCronjob implements ICronjob
{

    private static int $interval = 1;

    private static Recipe|null $translateObject = null;

    public static function interval(): int
    {
        return self::$interval;
    }

    public static function prepare(\Ahc\Cli\IO\Interactor &$io, array &$cache): bool
    {
        global $Controller;
        if (!$Controller->aiAvailable()) {
            $io->error(sprintf('%d %s', __LINE__, 'TranslateRecipesCronjob::prepare() -> AI API key not configured!'), true);
            return false;
        }
        try {
            $io->write(sprintf('%d %s', __LINE__, 'TranslateRecipesCronjob::prepare()'), true);
            $stmt = $Controller->prepare('SELECT * FROM allrecipes WHERE localized = 0 AND recipe_placeholder = 0 ORDER BY RAND() LIMIT 1');
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($recipe = $result->fetch_object(Recipe::class)) {
                    $recipe->loadComplete();
                    self::$translateObject = $recipe;
                }
                return !is_null(self::$translateObject);
            }
        } catch (Exception $e) {
        }
        return false;
    }

    public static function execute(\Ahc\Cli\IO\Interactor &$io, array &$cache): bool
    {
        global $Controller;
        $out = [
            'started' => time(),
            'finished' => false,
            'error' => null,
        ];
        $result = false;

        $io->write(sprintf('%d %s', __LINE__, 'TranslateRecipesCronjob::execute()'), true);

        $messages = [
            [
                'role' => 'system',
                'content' => 'A user of my cookbook has entered a new recipe. Please translate the '
                    . 'following JSON structure, which contains the recipe data. First, determine the '
                    . 'input language, either German or English. Only properties that contain counterparts '
                    . 'with the suffixes _de and _en need to be translated, such as the name property. '
                    . 'If the input language is German, transfer the content to the _de property and '
                    . 'only translate into English in the _en property. The same applies, of course, '
                    . 'for English in reverse. '
                    . 'The properties to be translated are: '
                    . 'In the first level, name and summary. '
                    . 'The property preparation is an array of further translatable elements. Please '
                    . 'translate title and instructions within it. '
                    . 'There may be an ingredients array included. In that case, please translate the '
                    . 'description property within it.
                    Here are the data:
                    ```json
                    ' . json_encode(self::$translateObject->jsonForLocalization()) . '
                    ```
                    Please process this data as per the instructions. Please reply with the processed JSON '
                    . 'only, do not include any comments or messages. In case of an error do reply with an '
                    . 'empty string.'
            ],
        ];

        $chat = false;
        try {
            $chat = $Controller->ai($messages);
            //$chat = '{"id":"chatcmpl-8TVN1Pqyui96ghQQQxjPewrV7NlHM","object":"chat.completion","created":1702042791,"model":"gpt-4-1106-vision-preview","usage":{"prompt_tokens":402,"completion_tokens":433,"total_tokens":835},"choices":[{"message":{"role":"assistant","content":"```json\n{\n  \"id\": 27,\n  \"name\": \"angebratene Schupfnudeln mit Erbsen und Karotten\",\n  \"name_de\": \"angebratene Schupfnudeln mit Erbsen und Karotten\",\n  \"name_en\": \"Saut\u00e9ed Potato Dumplings with Peas and Carrots\",\n  \"summary\": \"\",\n  \"summary_de\": \"\",\n  \"summary_en\": \"\",\n  \"preparation\": [\n    {\n      \"id\": 68,\n      \"title\": \"\",\n      \"instructions\": \"Leberk\u00e4se in W\u00fcrfel schneiden und mit den Schupfnudeln in einer Pfanne anbraten.\r\nSp\u00e4ter die Karotten und Erbsen dazu geben. \r\n\",\n      \"title_de\": \"\",\n      \"title_en\": \"\",\n      \"instructions_de\": \"Leberk\u00e4se in W\u00fcrfel schneiden und mit den Schupfnudeln in einer Pfanne anbraten.\r\nSp\u00e4ter die Karotten und Erbsen dazu geben. \r\n\",\n      \"instructions_en\": \"Cut the Leberk\u00e4se into cubes and saut\u00e9 with the potato dumplings in a pan.\r\nAdd the carrots and peas later.\r\n\",\n      \"ingredients\": []\n    }\n  ],\n  \"ingredients\": [\n    {\n      \"id\": 219,\n      \"description\": \"Schupfnudeln\",\n      \"description_de\": \"Schupfnudeln\",\n      \"description_en\": \"Potato Dumplings\"\n    },\n    {\n      \"id\": 220,\n      \"description\": \"Scheiben Leberk\u00e4se \",\n      \"description_de\": \"Scheiben Leberk\u00e4se \",\n      \"description_en\": \"Slices of Leberk\u00e4se\"\n    },\n    {\n      \"id\": 221,\n      \"description\": \"Erbsen und Karotten\",\n      \"description_de\": \"Erbsen und Karotten\",\n      \"description_en\": \"Peas and Carrots\"\n    }\n  ]\n}\n```"},"finish_details":{"type":"stop","stop":"<|fim_suffix|>"},"index":0}]}';
            // $io->write(sprintf('%d %s', __LINE__, $chat), true);
        } catch (Exception $e) {
            $io->write(sprintf('%d %s', __LINE__, $e->getMessage()), true);
            $out['error'] = $e->getMessage();
        }

        $responseObject = new AiResponse($chat);
        if ($responseObject->success && !is_null($responseObject->jsonPayload)) {

            if (!is_array($responseObject->jsonPayload) || !array_key_exists('id', $responseObject->jsonPayload)) {
                $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Failed transforming AI reply', [$responseObject->jsonPayload]);
                $out['error'] = 'Failed transforming AI reply';
            }

            if (self::$translateObject->getId() !== intval($responseObject->jsonPayload['id'])) {
                $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'AI returned different id!', [$responseObject->jsonPayload]);
                $out['error'] = 'AI returned different id!';
            }

            if ($Controller->startTransaction()) {
                try {
                    $io->write(sprintf('%d %s', __LINE__, json_encode($responseObject->jsonPayload)), true);
                    if (@self::$translateObject->updateLocalization($responseObject->jsonPayload))
                        $Controller->finishTransaction();
                } catch (Exception $e) {
                    $Controller->cancelTransaction();
                }
            }

        }

        $cache[self::class] = $out;
        return $result;

    }

}