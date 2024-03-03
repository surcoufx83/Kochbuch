<?php

namespace Surcouf\Cookbook\Controller\Routes\User;

use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;

if (!defined('CORE2'))
  exit;

class OAuth2GetParamsRoute extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;
    $provider = $Controller->getOAuthProvider();
    $url = $provider->getAuthorizationUrl();
    $urlparts = parse_url($url);
    $urlquery = null;
    parse_str($urlparts['query'], $urlquery);
    setcookie('state', $urlquery['state']);
    $response['params'] = [
      'url' => $url,
    ];
    $Controller->e200_Ok();
  }

}
