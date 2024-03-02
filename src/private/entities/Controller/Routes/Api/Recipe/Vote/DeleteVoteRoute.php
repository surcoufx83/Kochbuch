<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Recipe\Vote;

use \DateTime;
use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;
use Surcouf\Cookbook\Recipe\Recipe;

if (!defined('CORE2'))
  exit;

class DeleteVoteRoute extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;
    $recipe = Recipe::load(intval($Controller->Dispatcher()->getFromMatches('id')));
    $deleteCooked = $Controller->Dispatcher()->getFromPayload('includeCookedRecords');

    if (is_null($deleteCooked))
      $deleteCooked = false;
    else
      $deleteCooked = intval($deleteCooked) === 1;

    if (is_null($recipe))
      $Controller->e404_NotFound(__CLASS__, __METHOD__, __LINE__, 'Recipe not found', [intval($Controller->Dispatcher()->getFromMatches('id'))]);

    // Recipe deleteVote() method will return integer return code to use as browser response
    $Controller->e($recipe->deleteVote($deleteCooked));
  }

}