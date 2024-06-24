<?php

namespace Surcouf\Cookbook\Cli\Cronjob;

interface ICronjob {
    public static function interval(): int;
    public static function prepare(\Ahc\Cli\IO\Interactor &$io, array &$cache): bool;
    public static function execute(\Ahc\Cli\IO\Interactor &$io, array &$cache): bool;
}