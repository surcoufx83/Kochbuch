<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Recipe;

use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;
use Surcouf\Cookbook\Recipe\Ingredients\Units\Unit;
use Surcouf\Cookbook\Recipe\Recipe;

if (!defined('CORE2'))
  exit;

class RecipeEditRoute extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;
    $recipe = Recipe::load(intval($Controller->Dispatcher()->getFromMatches('id')));

    if (is_null($recipe))
      $Controller->e404_NotFound(__CLASS__, __METHOD__, __LINE__, 'Recipe not found', [intval($Controller->Dispatcher()->getFromMatches('id'))]);

    if (!array_key_exists('recipe', $_POST))
      $Controller->e400_BadRequest(__CLASS__, __METHOD__, __LINE__, 'Missing recipe data', [intval($Controller->Dispatcher()->getFromMatches('id'))]);

    $recipe->loadComplete();

    Unit::loadAll();

    $messageCode = 0;
    $userError = false;
    $code = $recipe->update($_POST['recipe'], $messageCode, $userError);
    $response = [
      'recipe' => $recipe,
      'errorCode' => +$messageCode,
      'userError' => $userError,
    ];
    $Controller->e($code);

    // Recipe delete() method will return integer return code to use as browser response
    // $Controller->e($recipe->delete());
  }
}
