<?php

namespace Surcouf\Cookbook\Cli\Cronjob;

use Exception;
use Surcouf\Cookbook\Cli\Cronjob\ICronjob;
use Surcouf\Cookbook\Response\AiResponse;

if (!defined('CORE2'))
    exit;

class TranslateUnitsCronjob implements ICronjob
{

    private static int $interval = 1;

    private static array $translateObjects = [];

    public static function interval(): int
    {
        return self::$interval;
    }

    public static function prepare(\Ahc\Cli\IO\Interactor &$io, array &$cache): bool
    {
        global $Controller;
        if (!$Controller->aiAvailable()) {
            $io->error(sprintf('%d %s', __LINE__, 'TranslateUnitsCronjob::prepare() -> AI API key not configured!'), true);
            return false;
        }
        try {
            $io->write(sprintf('%d %s', __LINE__, 'TranslateUnitsCronjob::prepare()'), true);
            $stmt = $Controller->prepare('SELECT unit_id, unit_name FROM units WHERE localized = 0 LIMIT 5');
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($record = $result->fetch_assoc()) {
                    self::$translateObjects[] = [
                        'id' => intval($record['unit_id']),
                        'name' => $record['unit_name'],
                        'lang' => '',
                        'name_de' => '',
                        'name_en' => '',
                    ];
                }
                return count(self::$translateObjects) > 0;
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

        $messages = [
            [
                'role' => 'system',
                'content' => 'I have a list of objects representing kitchen measurement units. Each object contains the fields `id`, '
                    . '`name`, `lang`, `name_de`, and `name_en`. The `id` and `name` should not be changed. `name` is the name of the unit, which '
                    . 'can be either in German or English. Your task is to determine the language of `name` and record it in the `lang` '
                    . 'field. Then, fill the `name_de` and `name_en` fields with arrays containing two strings each: the singular '
                    . 'form, and the plural form of the units. Always use common abbreviations found in cookbooks and exclude the numbers '
                    . '0 and 1 from these strings.
                    Here are the data:
                    ```json
                    ' . json_encode(self::$translateObjects) . '
                    ```
                    Please process this data as per the instructions.'
            ],
        ];

        try {
            $chat = $Controller->ai($messages);
            $io->write(sprintf('%d %s', __LINE__, $chat), true);
        } catch (Exception $e) {
            $io->write(sprintf('%d %s', __LINE__, $e->getMessage()), true);
            $out['error'] = $e->getMessage();
        }

        $responseObject = new AiResponse($chat);
        if ($responseObject->success && !is_null($responseObject->jsonPayload)) {
            $query = 'UPDATE units SET localized = 1, unit_name_de = ?, unit_name_en = ? WHERE unit_id = ?';
            $stmt = $Controller->prepare($query);
            for ($i = 0; $i < count($responseObject->jsonPayload); $i++) {
                $nameDe = json_encode($responseObject->jsonPayload[$i]['name_de']);
                $nameEn = json_encode($responseObject->jsonPayload[$i]['name_en']);
                $stmt->bind_param('ssi', $nameDe, $nameEn, $responseObject->jsonPayload[$i]['id']);
                if (!$stmt->execute()) {
                    $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Failed executing stmt', [$stmt->error]);
                    $out['error'] = $stmt->error;
                } else {
                    $out['finished'] = time();
                    $result = true;
                }
            }
        }

        $cache[self::class] = $out;
        return $result;

    }

}