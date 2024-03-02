<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Recipe;

use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;
use Surcouf\Cookbook\Recipe\Recipe;

if (!defined('CORE2'))
  exit;

class RecipeCreateRoute extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;

    $recipe = Recipe::createPlaceholder();
    if (is_null($recipe))
      $Controller->e500_ServerError();

    $response = [
      'recipe' => $recipe
    ];

    $Controller->e201_Created();

  }

}