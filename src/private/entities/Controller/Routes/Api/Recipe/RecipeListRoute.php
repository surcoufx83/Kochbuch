<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Recipe;

use DateTime;
use DateTimeZone;
use mysqli_stmt;
use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;
use Surcouf\Cookbook\Database\EAggregationType;
use Surcouf\Cookbook\Database\EQueryType;
use Surcouf\Cookbook\Database\QueryBuilder;
use Surcouf\Cookbook\Recipe\Recipe;
use Surcouf\Cookbook\User\User;

if (!defined('CORE2'))
  exit;

class RecipeListRoute extends Route implements RouteInterface
{

  private static string $defaultWhereClause = '(recipe_public_external = 1 OR ( recipe_public_internal = 1 AND ? = 1 ))';

  private static bool $isLoggedIn = false;
  private static int $isLoggedInInt = 0;
  private static int $isLoggedInUserId = 0;
  private static string $queryparamFilter = '';
  private static string $queryparamGroup = 'home';
  private static int|null $applyFilterId = null;
  private static int|null $applyListLength = null;

  static function createOutput(array &$response): void
  {
    global $Controller;

    self::$isLoggedIn = !is_null($Controller->User());
    self::$isLoggedInInt = self::$isLoggedIn ? 1 : 0;
    self::$isLoggedInUserId = self::$isLoggedIn ? $Controller->User()->getId() : 0;
    self::$applyListLength = intval($Controller->Config()->Defaults('Lists', 'Entries'));

    $param = $Controller->Dispatcher()->getFromMatches('group');
    if (!is_null($param))
      self::$queryparamGroup = $param;
    $param = $Controller->Dispatcher()->getFromMatches('filter');
    if (!is_null($param))
      self::$queryparamFilter = $param;

    if (self::$queryparamGroup === 'my' && !self::$isLoggedIn) {
      $Controller->e403_Forbidden(__CLASS__, __METHOD__, __LINE__, 'Requested own recipes but user not logged in.', []);
    }

    if (!is_null(self::$queryparamFilter)) {
      $queryFilter = explode('&', self::$queryparamFilter);
      for ($i = 0; $i < count($queryFilter); $i++) {
        $key = $queryFilter[$i];
        $value = null;
        if (strpos($queryFilter[$i], '=') > -1) {
          $key = substr($queryFilter[$i], 0, strpos($queryFilter[$i], '='));
          $value = substr($queryFilter[$i], strlen($key) + 1);
        }
        switch ($key) {
          case 'count':
            if (intval($value) > 0 && intval($value) < 1000)
              self::$applyListLength = intval($value);
            break;
          case 'id':
            self::$applyFilterId = intval($value);
            break;
        }
      }
    }

    if (self::$queryparamGroup === 'user' && is_null(self::$applyFilterId)) {
      $Controller->e404_NotFound(__CLASS__, __METHOD__, __LINE__, 'Requested user recipes list but no userid given.', []);
    }

    $ts = self::lastActivityDate();
    if (is_null($ts))
      $ts = new DateTime();
    $response = [
      'ts' => $ts->format('c'),
      'count' => 0,
      'limit' => self::$applyListLength,
      'list' => [],
    ];

    self::executeRecipeQueries($response);

  }

  private static function executeRecipeQueries(array &$response): void
  {
    switch (self::$queryparamGroup) {
      case 'home':
        self::executeRecipeQueries_Home($response);
        break;
      case 'my':
        self::executeRecipeQueries_My($response);
        break;
      case 'user':
        self::executeRecipeQueries_User($response);
        break;
    }
  }

  private static function executeRecipeQueriesMainCount(array &$response, mysqli_stmt $countquery): void
  {
    $countquery->execute();
    $response['count'] = intval($countquery->get_result()->fetch_assoc()['count']);
  }

  private static function executeRecipeQueriesMainData(array &$response, mysqli_stmt $dataquery): void
  {
    $dataquery->execute();
    $result = $dataquery->get_result();
    while ($recipe = $result->fetch_object(Recipe::class)) {
      $recipe->loadForListings();
      $response['list'][] = $recipe;
    }
  }

  private static function executeRecipeQueries_Home(array &$response): void
  {
    global $Controller;
    $countquery = 'SELECT COUNT(*) AS count FROM allrecipes WHERE ' . self::$defaultWhereClause . ' AND picture_id IS NOT NULL';
    $dataquery = 'SELECT * FROM allrecipes WHERE ' . self::$defaultWhereClause . ' AND picture_id IS NOT NULL ORDER BY recipe_published DESC LIMIT ?';

    $stmt = $Controller->prepare($countquery);
    $stmt->bind_param('i', self::$isLoggedInInt);
    self::executeRecipeQueriesMainCount($response, $stmt);

    $stmt = $Controller->prepare($dataquery);
    $stmt->bind_param('ii', self::$isLoggedInInt, self::$applyListLength);
    self::executeRecipeQueriesMainData($response, $stmt);
  }

  private static function executeRecipeQueries_My(array &$response): void
  {
    global $Controller;
    $countquery = 'SELECT COUNT(*) AS count FROM allrecipes WHERE user_id = ? AND recipe_placeholder = 0';
    $dataquery = 'SELECT * FROM allrecipes WHERE user_id = ? AND recipe_placeholder = 0 ORDER BY recipe_name ASC LIMIT ?';

    $stmt = $Controller->prepare($countquery);
    $stmt->bind_param('i', self::$isLoggedInUserId);
    self::executeRecipeQueriesMainCount($response, $stmt);

    $stmt = $Controller->prepare($dataquery);
    $stmt->bind_param('ii', self::$isLoggedInUserId, self::$applyListLength);
    self::executeRecipeQueriesMainData($response, $stmt);
  }

  private static function executeRecipeQueries_User(array &$response): void
  {
    global $Controller;

    $user = User::load(self::$applyFilterId);
    if (is_null($user)) {
      $Controller->e404_NotFound(__CLASS__, __METHOD__, __LINE__, 'Requested user recipes list but no userid is invalid.', []);
    }

    $countquery = 'SELECT COUNT(*) AS count FROM allrecipes WHERE ' . self::$defaultWhereClause . ' AND user_id = ?';
    $dataquery = 'SELECT * FROM allrecipes WHERE ' . self::$defaultWhereClause . ' AND user_id = ? ORDER BY recipe_name ASC LIMIT ?';

    $stmt = $Controller->prepare($countquery);
    $stmt->bind_param('ii', self::$isLoggedInInt, self::$applyFilterId);
    self::executeRecipeQueriesMainCount($response, $stmt);

    $stmt = $Controller->prepare($dataquery);
    $stmt->bind_param('iii', self::$isLoggedInInt, self::$applyFilterId, self::$applyListLength);
    self::executeRecipeQueriesMainData($response, $stmt);

    $response['user'] = $user;
  }

  private static function lastActivityDate(): ?DateTime
  {
    global $Controller;
    $stmt = $Controller->prepare('SELECT ts FROM lastactivityview');
    if ($stmt->execute()) {
      $result = $stmt->get_result()->fetch_assoc();
      return new DateTime($result['ts'], new DateTimeZone('UTC'));
    }
    return null;
  }

  static function isAllowed(): bool
  {
    return true;
  }

}