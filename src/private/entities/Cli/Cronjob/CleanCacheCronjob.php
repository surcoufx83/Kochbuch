<?php

namespace Surcouf\Cookbook\Cli\Cronjob;

use Exception;
use Surcouf\Cookbook\Cli\Cronjob\ICronjob;

if(!defined('CORE2'))
    exit;

class CleanCacheCronjob implements ICronjob {

    private static int $interval = 86400;

    public static function interval(): int {
        return self::$interval;
    }

    public static function prepare(\Ahc\Cli\IO\Interactor &$io, array &$cache): bool {
        $io->write(sprintf('%d %s', __LINE__, 'CleanCacheCronjob::prepare()'), true);
        return true;
    }

    public static function execute(\Ahc\Cli\IO\Interactor &$io, array &$cache): bool {
        $io->write(sprintf('%d %s', __LINE__, 'CleanCacheCronjob::execute()'), true);
        $out = [
            'started' => time(),
            'finished' => false,
            'error' => null,
        ];
        try {
            if(
                self::clearApiCache($io) &&
                self::clearApiLogInfo($io) &&
                self::clearApiLogAny($io)
            )
                $out['finished'] = time();
        } catch (Exception $e) {
            $out['error'] = $e->getMessage();
        }
        $cache[self::class] = $out;
        return !is_null($out['finished']);
    }

    private static function clearApiCache(\Ahc\Cli\IO\Interactor &$io): bool {
        $io->write(sprintf('%d %s', __LINE__, 'CleanCacheCronjob::clearApiCache()'), true);
        global $Controller;
        $stmt = $Controller->prepare('DELETE FROM apicache WHERE ts < (SELECT ts FROM lastactivityview)', false);
        return $stmt->execute();
    }

    private static function clearApiLogAny(\Ahc\Cli\IO\Interactor &$io): bool {
        $io->write(sprintf('%d %s', __LINE__, 'CleanCacheCronjob::clearApiLogAny()'), true);
        global $Controller;
        $stmt = $Controller->prepare('DELETE FROM apilog WHERE severity != \'I\' AND `when` < DATE_SUB(NOW(), INTERVAL 30 DAY)', false);
        return $stmt->execute();
    }

    private static function clearApiLogInfo(\Ahc\Cli\IO\Interactor &$io): bool {
        $io->write(sprintf('%d %s', __LINE__, 'CleanCacheCronjob::clearApiLogInfo()'), true);
        global $Controller;
        $stmt = $Controller->prepare('DELETE FROM apilog WHERE severity = \'I\' AND `when` < DATE_SUB(NOW(), INTERVAL 1 DAY)', false);
        return $stmt->execute();
    }

}