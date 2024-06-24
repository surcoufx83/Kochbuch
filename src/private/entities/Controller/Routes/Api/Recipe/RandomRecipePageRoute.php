<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Recipe;

use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;

if (!defined('CORE2'))
  exit;

class RandomRecipePageRoute extends Route implements RouteInterface
{

  private static int $isLoggedInInt = 0;

  static function createOutput(array &$response): void
  {
    global $Controller;
    $matchid = $Controller->Dispatcher()->getFromMatches('id');
    $matchid = !is_null($matchid) ? intval($matchid) : 0;
    self::$isLoggedInInt = !is_null($Controller->User()) ? 1 : 0;

    $stmt = $Controller->prepare('SELECT recipe_id, recipe_name FROM allrecipes WHERE (recipe_public_external = 1 OR ( recipe_public_internal = 1 AND ? = 1 )) AND recipe_id != ? ORDER BY RAND() LIMIT 1');
    $stmt->bind_param('ii', self::$isLoggedInInt, $matchid);

    if (!$stmt->execute())
      $Controller->e500_ServerError();

    $record = $stmt->get_result()->fetch_assoc();
    $response = [
      'id' => $record['recipe_id'],
      'name' => $record['recipe_name'],
    ];
    $Controller->e200_Ok();

  }

}