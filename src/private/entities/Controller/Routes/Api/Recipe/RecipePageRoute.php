<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Recipe;

use \DateTime;
use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;
use Surcouf\Cookbook\Recipe\Recipe;

if (!defined('CORE2'))
  exit;

class RecipePageRoute extends Route implements RouteInterface
{

  static private int|null $recipeid = null;
  static private Recipe|null $recipe = null;
  static private bool $isLoggedIn = false;

  static function createOutput(array &$response): void
  {
    global $Controller;

    if (is_null(self::$recipe))
      $Controller->e404_NotFound();

    if (!self::$recipe->mayIRead())
      $Controller->e403_Forbidden();

    self::$recipe->loadComplete();
    $response = self::$recipe->jsonSerialize();
    $Controller->e200_Ok();
  }

  static function isAllowed(): bool
  {
    global $Controller;
    self::$recipeid = intval($Controller->Dispatcher()->getFromMatches('id'));
    self::$isLoggedIn = !is_null($Controller->User());
    self::$recipe = Recipe::load(self::$recipeid);
    if (is_null(self::$recipe))
      return true;
    return self::$recipe->mayIRead();
  }

}