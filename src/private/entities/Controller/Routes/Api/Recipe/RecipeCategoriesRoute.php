<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Recipe;

use DateTime;
use DateTimeZone;
use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;

if (!defined('CORE2'))
  exit;

class RecipeCategoriesRoute extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;

    $categories = [];
    $stmt = $Controller->prepare('SELECT * FROM categoryitemsview');
    if ($stmt->execute()) {
      $result = $stmt->get_result();
      while ($record = $result->fetch_assoc()) {
        if (!array_key_exists($record['catname'], $categories))
          $categories[$record['catname']] = [
            'icon' => $record['caticon'],
            'id' => intval($record['catid']),
            'modified' => $record['catmodified'],
            'techname' => $record['catname'],
            'items' => [],
          ];
        $categories[$record['catname']]['items'][$record['itemname']] = [
          'icon' => $record['itemicon'],
          'id' => intval($record['itemid']),
          'modified' => $record['itemmodified'],
          'techname' => $record['itemname'],
        ];
      }
    }

    $response = [
      'ts' => (new DateTime('now', new DateTimeZone('UTC')))->format('c'),
      'list' => $categories,
    ];

    $Controller->e200_Ok();
  }

  static function isAllowed(): bool
  {
    return true;
  }

}