<?php

namespace Surcouf\Cookbook\Controller;

if (!defined('CORE2'))
  exit;

class Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
  }

  static function isAllowed(): bool
  {
    return false;
  }

}