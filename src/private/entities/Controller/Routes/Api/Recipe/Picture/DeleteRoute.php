<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Recipe\Picture;

use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;
use Surcouf\Cookbook\Recipe\Pictures\Picture;
use Surcouf\Cookbook\Recipe\Recipe;

if (!defined('CORE2'))
  exit;

class DeleteRoute extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;
    $recipe = Recipe::load(intval($Controller->Dispatcher()->getFromMatches('recipeid')));

    if (is_null($recipe)) {
      $response = [
        'errorCode' => 1,
        'recipe' => null,
        'userError' => false,
      ];
      $Controller->e404_NotFound(__CLASS__, __METHOD__, __LINE__, 'Recipe not found', [intval($Controller->Dispatcher()->getFromMatches('id'))]);
    }

    $pictureid = intval(intval($Controller->Dispatcher()->getFromMatches('pictureid')));
    $recipe->loadComplete();

    if (!$Controller->startTransaction()) {
      $response = [
        'errorCode' => 5,
        'recipe' => null,
        'userError' => false,
      ];
      $Controller->e500_ServerError(__CLASS__, __METHOD__, __LINE__, 'Unable to start transaction', $Controller->dberror2());
    }

    if (!$recipe->deletePicture($pictureid, $response)) {
      $Controller->cancelTransaction();
      $Controller->e500_ServerError(__CLASS__, __METHOD__, __LINE__, 'Unable to delete picture object and move uploaded file.');
    }

    if (!$Controller->finishTransaction()) {
      $response = [
        'errorCode' => 8,
        'recipe' => null,
        'userError' => false,
      ];
      $Controller->e500_ServerError(__CLASS__, __METHOD__, __LINE__, 'Unable to start transaction', $Controller->dberror2());
    }

    $response = [
      'errorCode' => 0,
      'recipe' => null,
      'userError' => false,
    ];

    $Controller->e202_Accepted();

  }

}