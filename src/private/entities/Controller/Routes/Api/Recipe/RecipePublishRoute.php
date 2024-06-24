<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Recipe;

use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;
use Surcouf\Cookbook\Recipe\Recipe;

if (!defined('CORE2'))
  exit;

class RecipePublishRoute extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;
    $recipe = Recipe::load(intval($Controller->Dispatcher()->getFromMatches('id')));

    if (is_null($recipe))
      $Controller->e404_NotFound(__CLASS__, __METHOD__, __LINE__, 'Recipe not found', [intval($Controller->Dispatcher()->getFromMatches('id'))]);

    $target = $Controller->Dispatcher()->getFromMatches('target');
    if (is_null($target) || ($target != 'private' && $target != 'internal' && $target != 'external'))
      $Controller->e400_BadRequest(__CLASS__, __METHOD__, __LINE__, 'Publish state target invalid', [intval($Controller->Dispatcher()->getFromMatches('id')), $target]);

    // Recipe publish() method will return integer return code to use as browser response
    $Controller->e($recipe->publish($target));
  }

}