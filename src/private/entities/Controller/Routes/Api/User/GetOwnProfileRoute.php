<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\User;

use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;

if (!defined('CORE2'))
  exit;

class GetOwnProfileRoute extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;
    $response['user'] = $Controller->User()->getJsonObj(false);
    $Controller->e200_Ok();
  }

}