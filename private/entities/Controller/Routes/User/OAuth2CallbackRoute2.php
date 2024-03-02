<?php

namespace Surcouf\Cookbook\Controller\Routes\User;

use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;

if (!defined('CORE2'))
  exit;

class OAuth2CallbackRoute2 extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;
    if ($Controller->isAuthenticated()) // if already logged in -> show homepage
      $Controller->e202_Accepted();
    $Controller->Dispatcher()->finishOAuthPostLogin($response); // in case of success -> dispatcher will forward the user
  }

}