<?php

namespace Surcouf\Cookbook\Controller\Routes\Api\Search;

use DateTime;
use DateTimeZone;
use Surcouf\Cookbook\Controller\Route;
use Surcouf\Cookbook\Controller\RouteInterface;
use Surcouf\Cookbook\Database\EAggregationType;
use Surcouf\Cookbook\Database\EQueryType;
use Surcouf\Cookbook\Database\MysqliStmtParamBuilder;
use Surcouf\Cookbook\Database\QueryBuilder;
use Surcouf\Cookbook\Database\Builder\Expression;
use Surcouf\Cookbook\Helper\Formatter;
use Surcouf\Cookbook\Recipe\Recipe;
use Surcouf\Cookbook\Recipe\Pictures\Picture;
use Surcouf\Cookbook\User\User;

if (!defined('CORE2'))
  exit;

class SearchRoute extends Route implements RouteInterface
{

  static function createOutput(array &$response): void
  {
    global $Controller;

    $searchphrase = '';
    $limit = 100;
    if (array_key_exists('query', $_GET))
      $searchphrase = $_GET['query'];
    if (is_null($searchphrase) || $searchphrase === '') {
      $Controller->logi(__CLASS__, __METHOD__, __LINE__, 'Missing query phrase', $_GET);
      $response = [];
      $Controller->e400_BadRequest();
    }

    if (array_key_exists('count', $_GET))
      $limit = $_GET['count'];
    if ($limit < 1 || $limit > 1000) {
      $Controller->logi(__CLASS__, __METHOD__, __LINE__, 'Request exceeds limit', $_GET);
      $response = [];
      $Controller->e400_BadRequest();
    }

    $searchingredients = array_key_exists('fi', $_GET) ? intval($_GET['fi']) === 1 : false;
    $searchrecipes = array_key_exists('fr', $_GET) ? intval($_GET['fr']) === 1 : false;
    $searchusers = array_key_exists('fu', $_GET) ? intval($_GET['fu']) === 1 : false;

    $response = [
      'ts' => (new DateTime('now', new DateTimeZone('UTC')))->format('c'),
      'count' => 0,
      'limit' => $limit,
      'recipes' => [],
      'user' => [],
    ];

    if ($searchrecipes || $searchingredients) {
      self::searchRecipes($response, $searchphrase, $limit, $searchrecipes, $searchingredients);
    }

    if ($searchusers) {
      self::searchUser($response, $searchphrase, $limit);
    }

    $Controller->e200_Ok();
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

  private static function searchRecipes(array &$response, string $searchphrase, int $limit, bool $inrecipe, bool $iningredients): void
  {
    global $Controller;
    $words = [];
    $buffer = '';
    $modifier = null;
    $insideQuotes = false;

    $column = 'recipe_fulltext';
    if (!$inrecipe)
      $column = 'recipe_ingredients';
    if (!$iningredients)
      $column = 'recipe_fulltext_noingredients';

    $isuser = is_null($Controller->User()) ? 0 : 1;

    for ($i = 0; $i < strlen($searchphrase); $i++) {
      $letter = $searchphrase[$i];

      if (($letter == '+' || $letter == '-') && empty($buffer)) {
        $modifier = $letter;
        continue;
      }
      if ($letter == '"') {
        $insideQuotes = !$insideQuotes;
      } else if ($letter == ' ' && !$insideQuotes) {
        if (!empty($buffer) && preg_match('/\w/i', $buffer) === 1) {
          $words[] = [
            'word' => '%' . str_replace('*', '%', $buffer) . '%',
            'mod' => (is_null($modifier) ? '+' : $modifier),
          ];
          $buffer = '';
          $modifier = null;
        }
      } else {
        $buffer .= $letter;
      }
    }

    if (!empty($buffer) && preg_match('/\w/i', $buffer) === 1) {
      $words[] = [
        'word' => '%' . str_replace('*', '%', $buffer) . '%',
        'mod' => (is_null($modifier) ? '+' : $modifier),
      ];
    }

    $searchitems = [];
    $searchwords = [$isuser];
    for ($i = 0; $i < count($words); $i++) {
      if ($words[$i]['mod'] == '+')
        $searchitems[] = ($i == 0 ? '' : ' AND ') . $column . ' LIKE ?';
      else
        $searchitems[] = ($i == 0 ? '' : ' AND ') . $column . ' NOT LIKE ?';
      $searchwords[] = $words[$i]['word'];
    }

    if (count($searchwords) == 0) {
      $Controller->logi(__CLASS__, __METHOD__, __LINE__, 'Query phrase does not contain helpful words', $_GET);
      $response = [];
      $Controller->e400_BadRequest();
    }

    $stmt = $Controller->prepare('SELECT COUNT(recipe_id) AS count FROM allrecipetextdata WHERE ((recipe_public_internal = 1 AND 1 = ?) OR recipe_public_external = 1) AND ' . join('', $searchitems));
    MysqliStmtParamBuilder::BindParams($stmt, 'i' . str_repeat('s', count($searchwords) - 1), $searchwords);

    if ($stmt->execute()) {
      $result = $stmt->get_result();
      while ($record = $result->fetch_assoc()) {
        $response['count'] = intval($record['count']);
      }
    }

    $searchwords[] = $limit;

    $stmt = $Controller->prepare('SELECT recipe_id FROM allrecipetextdata WHERE ((recipe_public_internal = 1 AND 1 = ?) OR recipe_public_external = 1) AND ' . join('', $searchitems) . ' ORDER BY recipe_name LIMIT ?');
    MysqliStmtParamBuilder::BindParams($stmt, 'i' . str_repeat('s', count($searchwords) - 2) . 'i', $searchwords);

    if ($stmt->execute()) {
      $result = $stmt->get_result();
      while ($record = $result->fetch_assoc()) {
        $recipe = Recipe::load(intval($record['recipe_id']));
        $recipe->loadForListings();
        $response['recipes'][] = $recipe;
      }
    }
  }
  private static function searchUser(array &$response, string $searchphrase, int $limit): void
  {
    global $Controller;
    $stmt = $Controller->prepare('SELECT * FROM users WHERE user_fullname LIKE ? ORDER BY user_fullname LIMIT ?');
    $userphrase = '%' . $searchphrase . '%';
    $stmt->bind_param('si', $userphrase, $limit);

    if ($stmt->execute()) {
      $result = $stmt->get_result();
      while ($record = $result->fetch_object(User::class)) {
        $response['user'][] = $record->getJsonObj();
      }
    }
  }

  static function isAllowed(): bool
  {
    return true;
  }

}