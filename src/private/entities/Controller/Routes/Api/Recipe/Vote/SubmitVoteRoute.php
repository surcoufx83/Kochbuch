<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Recipe\Vote;

use \DateTime;
use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;
use Surcouf\Cookbook\Recipe\Recipe;

if (!defined('CORE2'))
  exit;

class SubmitVoteRoute extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;
    $recipe = Recipe::load(intval($Controller->Dispatcher()->getFromMatches('id')));

    if (is_null($recipe))
      $Controller->e404_NotFound(__CLASS__, __METHOD__, __LINE__, 'Recipe not found', [intval($Controller->Dispatcher()->getFromMatches('id'))]);

    $voteCooked = intval($Controller->Dispatcher()->getFromPayload('cooked'));
    $voteDiff = intval($Controller->Dispatcher()->getFromPayload('difficulty'));
    $voteHearts = intval($Controller->Dispatcher()->getFromPayload('hearts'));

    // Recipe vote() method will return integer return code to use as browser response
    $Controller->e($recipe->vote($voteCooked, $voteDiff, $voteHearts));
    
  }

}