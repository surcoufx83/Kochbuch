<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\User;

use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;

if (!defined('CORE2'))
  exit;

class LogoutRoute extends Route implements RouteInterface {

  static function createOutput(array &$response) : void {
    global $Controller;
    $Controller->logout();
    $response = null;
    $Controller->e202_Accepted();
  }

}
