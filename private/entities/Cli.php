<?php

namespace Surcouf\Cookbook;

use Surcouf\Cookbook\Cli\Command\CronjobCommand;
use Throwable;

if (!defined('CORE2'))
    exit;

class Cli
{

    private $cliApp;

    public function __construct()
    {
        $this->cliApp = new \Ahc\Cli\Application('Cookbook CLI', '1.0.0');
        $this->cliApp->add(new CronjobCommand, 'c');
    }

    public function handle(array $argv)
    {
        $this->cliApp
            ->handle($argv);
    }

}