<?php

namespace Surcouf\Cookbook\Controller;

use Surcouf\Cookbook\Helper\UiHelper\CarouselHelper;

if (!defined('CORE2'))
  exit;

interface RouteInterface
{

  static function createOutput(array &$response): void;
  static function isAllowed(): bool;

}
