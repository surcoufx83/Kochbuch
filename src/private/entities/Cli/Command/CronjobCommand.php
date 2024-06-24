<?php

namespace Surcouf\Cookbook\Cli\Command;

use Surcouf\Cookbook\Cli\Cronjob\CleanCacheCronjob;
use Surcouf\Cookbook\Cli\Cronjob\TranslateRecipesCronjob;
use Surcouf\Cookbook\Cli\Cronjob\TranslateUnitsCronjob;

if (!defined('CORE2'))
    exit;

class CronjobCommand extends \Ahc\Cli\Input\Command
{

    private array|null $cache = null;
    private string $cachefile = __DIR__ . '/cronjobs.json';

    public function __construct()
    {
        parent::__construct('cron:run', 'Execute cronjobs');
    }

    public function execute(): void
    {
        $io = $this->app()->io();
        $io->write(sprintf('%d %s', __LINE__, (new \DateTime('now'))->format('r')), true);
        $io->write(sprintf('%d %s', __LINE__, 'CronjobCommand::execute()'), true);
        $io->write(sprintf('%d %s', __LINE__, $this->cachefile), true);
        $this->loadCache($io);
        $this->runCronjobs($io);
        $this->saveCache($io);
    }

    private function isDue(string $classname, int $interval): bool
    {
        $io = $this->app()->io();
        $io->write(sprintf('%d CronjobCommand::isDue(%s, %s)', __LINE__, $classname, $interval), true);
        if (!array_key_exists($classname, $this->cache))
            return true;
        return $this->cache[$classname]['started'] <= time() - $interval;
    }

    private function loadCache(\Ahc\Cli\IO\Interactor &$io): void
    {
        $io->write(sprintf('%d %s', __LINE__, 'CronjobCommand::loadCache()'), true);
        if (@file_exists($this->cachefile)) {
            $this->cache = json_decode(file_get_contents($this->cachefile), true);
        } else {
            $this->cache = [];
        }
    }

    private function runCronjobs(\Ahc\Cli\IO\Interactor &$io): void
    {
        if ($this->isDue(CleanCacheCronjob::class, CleanCacheCronjob::interval()) && CleanCacheCronjob::prepare($io, $this->cache)) {
            CleanCacheCronjob::execute($io, $this->cache);
        }
        if ($this->isDue(TranslateUnitsCronjob::class, TranslateUnitsCronjob::interval()) && TranslateUnitsCronjob::prepare($io, $this->cache)) {
            TranslateUnitsCronjob::execute($io, $this->cache);
        }
        if ($this->isDue(TranslateRecipesCronjob::class, TranslateRecipesCronjob::interval()) && TranslateRecipesCronjob::prepare($io, $this->cache)) {
            TranslateRecipesCronjob::execute($io, $this->cache);
        }
    }

    private function saveCache(\Ahc\Cli\IO\Interactor &$io): void
    {
        $io->write(sprintf('%d %s', __LINE__, 'CronjobCommand::saveCache()'), true);
        file_put_contents($this->cachefile, json_encode($this->cache, JSON_PRETTY_PRINT));
    }

}