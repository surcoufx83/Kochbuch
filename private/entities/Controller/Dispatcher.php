<?php

namespace Surcouf\Cookbook\Controller;

use Exception;
use Surcouf\Cookbook\Database\EQueryType;
use Surcouf\Cookbook\Database\QueryBuilder;
use Surcouf\Cookbook\Request\ERequestMethod;
use Surcouf\Cookbook\Response\EOutputMode;
use Laravie\Parser\Xml\Reader;
use Laravie\Parser\Xml\Document;

if (!defined('CORE2'))
  exit;

final class Dispatcher
{

  private $matched = false,
  $matchCacheable = false,
  $matchCacheRoute = '',
  $preventCacheUpdate = true,
  $matchedGroups = null,
  $matchedHandler = null,
  $matchedObject = null,
  $matchedPayloadRequirements = false,
  $matchedPattern = null,
  $outputMode = EOutputMode::JSON,
  $pageProperties = [],
  $requestMethod = ERequestMethod::Unknown;

  private array $routePatternMatches;

  private $postdata = [];


  function __construct()
  {
    if (ISWEB)
      $this->requestMethod = $this->getHttpRequestMethod();
    else
      $this->requestMethod = ERequestMethod::CLI;
    $this->postdata = array_merge_recursive($this->postdata, $_POST, json_decode(file_get_contents("php://input"), true) ?? []);
  }

  /**
   * Registers a routing information for output to the web browser if the
   * defaults specified in the $params array match the current page request.
   * @param string $routePattern A regex pattern which defines the expected url for this route.
   * @param array $params An associative array with the routing information.
   * @return bool true if route matches the current request.
   */
  public function addRoute(string $routePattern, array $params): bool
  {
    global $Controller;

    if (
      $this->evaluateRouteMaintenance($params)
      && $this->evaluateRouteMethod($params)
      && $this->evaluateRoutePattern($routePattern)
      && $this->evaluateRouteUser($params)
    ) {
      $user = $Controller->User();
      $this->matched = true;
      $this->matchCacheable = $params['cacheable'];
      $this->matchCacheRoute = sprintf('%s %s u=%d', $this->requestMethod, $_SERVER['REQUEST_URI'], ($params['cacheByUser'] ? (!is_null($user) ? $user->getId() : 0) : -1));
      $this->matchedGroups = $this->routePatternMatches;
      $this->matchedPattern = $routePattern;
      $this->matchedHandler = $params['class'];
      $this->matchedPayloadRequirements = $this->evaluateRoutePayload($params);

      if (array_key_exists('properties', $params))
        $this->pageProperties = $params['properties'];

      /* if (array_key_exists('createObject', $params)) {
        $method = $params['createObject']['method'];
        $this->matchedObject = $Controller->OM()->$method(intval($this->getFromMatches($params['createObject']['idkey'])));
      } */

      return true;
    }

    return false;
  }

  /**
   * Generates the output data for the called Url.
   */
  public function dispatchRoute(array &$response): void
  {
    global $Controller;

    if (!$this->matched) {
      $Controller->e404_NotFound(__CLASS__, __METHOD__, __LINE__, 'No route matched');
    }

    if ($this->requestMethod == ERequestMethod::HTTP_POST && count($_POST) == 0) {
      $_POST = json_decode(file_get_contents("php://input"), true);
    }

    // caching mechanism
    if ($this->dispatchFromCache($response)) {
      exit;
    }

    $this->preventCacheUpdate = false;
    $this->matchedHandler::createOutput($response);

    $Controller->e200_Ok();
    exit;
  }

  private function dispatchFromCache(array &$response): bool
  {
    global $Controller;
    $allowed = $this->matchedHandler::isAllowed();
    if ($this->matchCacheable && $this->matchCacheRoute != '') {
      $stmt = $Controller->prepare('SELECT * FROM `apicache_view` WHERE `route` = ?');
      $stmt->bind_param('s', $this->matchCacheRoute);
      if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows != 0 && $allowed) {
          $result = $result->fetch_assoc();
          $response = json_decode($result['response'], true);
          $response['cache'] = true;
          $Controller->e200_Ok();
          return true;
        }
      }
    }
    return false;
  }

  public function dispatchToCache(array &$response): void
  {
    if ($this->preventCacheUpdate)
      return;
    global $Controller;
    if ($this->matchCacheable && $this->matchCacheRoute != '') {
      $stmt = $Controller->prepare('INSERT INTO `apicache`(`route`, `ts`, `response`) VALUES (?, (SELECT `ts` FROM `lastactivityview` LIMIT 1) , ?)');
      $dbresponse = json_encode($response);
      $stmt->bind_param('ss', $this->matchCacheRoute, $dbresponse);
      try {
        $stmt->execute();
      } catch (Exception $e) {
        $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Failed inserting cache', [$stmt->error]);
      }
    }
  }

  /**
   * Checks if request is available for active maintenance mode.
   * @param array $params An associative array with the routing information.
   * @return bool true if matching method.
   */
  private function evaluateRouteMaintenance(array $params): bool
  {
    if (
      (array_key_exists('ignoreMaintenance', $params) && $params['ignoreMaintenance'] === true && MAINTENANCE == true) ||
      MAINTENANCE === false
    )
      return true;
    return false;
  }

  /**
   * Checks if the HTTP REQUEST_METHOD matches the method required for the
   * function.
   * @param array $params An associative array with the routing information.
   * @return bool true if matching method.
   */
  private function evaluateRouteMethod(array $params): bool
  {
    if (
      (!array_key_exists('method', $params) && $this->requestMethod == ERequestMethod::HTTP_GET) ||
      (array_key_exists('method', $params) && $params['method'] == $this->requestMethod)
    )
      return true;
    return false;
  }

  /**
   * Checks if the requested page Url matches the route pattern.
   * @param string $routePattern A regex pattern which defines the expected url for this route.
   * @return bool true if matching pattern.
   */
  private function evaluateRoutePattern(string $routePattern): bool
  {
    $pattern = str_replace('/', '\\/', $routePattern);
    $this->routePatternMatches = array();
    if (preg_match('/^' . $pattern . '$/', $_SERVER['REQUEST_URI'], $this->routePatternMatches)) {
      return true;
    }
    return false;
  }

  /**
   * If the page is called via HTTP POST, the route may require certain POST
   * information (payload). This function checks if this information is
   * available in $_POST.
   * @param array $params An associative array with the routing information.
   * @return bool true if matching method.
   */
  private function evaluateRoutePayload(array $params): bool
  {
    if ($this->requestMethod != ERequestMethod::HTTP_POST)
      return true;
    if (array_key_exists('requiresPayload', $params)) {
      $payload = $params['requiresPayload'];
      if (is_string($payload))
        return array_key_exists($payload, $_POST);
      for ($i = 0; $i < count($payload); $i++) {
        if (!array_key_exists($payload[$i], $_POST))
          return false;
      }
    }
    return true;
  }

  /**
   * Checks if the requirements for user authentication are fullfilled.
   * @param array $params An associative array with the routing information.
   * @return bool true if matching method.
   */
  private function evaluateRouteUser(array $params): bool
  {
    global $Controller;
    if (!$Controller->isAuthenticated()) {
      // if no user logged in, route must define 'requiresUser' with false
      // and must not define 'requiresAdmin'
      if (
        !array_key_exists('requiresUser', $params) ||
        $params['requiresUser'] !== false ||
        array_key_exists('requiresAdmin', $params)
      )
        $Controller->e403_Forbidden();
      // $this->forwardTo($Controller->getLink('private:login'));
    } else {
      // if route requires admin check if user is admin
      if (
        array_key_exists('requiresAdmin', $params) &&
        $params['requiresAdmin'] !== false &&
        !$Controller->User()->isAdmin()
      )
        return false;
    }
    return true;
  }

  public function finishOAuthPostLogin(array &$response): bool
  {
    global $Controller;
    $provider = $Controller->getOAuthProvider();
    $Controller->logi(__CLASS__, __METHOD__, __LINE__, 'Requesting access with code', [$_POST['code']]);

    if (!array_key_exists('state', $_POST) || !array_key_exists('code', $_POST)) {
      $Controller->e401_Unauthorized(__CLASS__, __METHOD__, __LINE__, 'State or Code missing', [$_POST]);
    }

    $accessToken = null;
    try {
      $accessToken = $provider->getAccessToken('authorization_code', [
        'code' => $_POST['code']
      ]);
    } catch (\Exception $e) {
      $Controller->e401_Unauthorized(__CLASS__, __METHOD__, __LINE__, $e->getMessage(), [$provider, $e]);
    }
    if (is_null($accessToken))
      $Controller->e401_Unauthorized(__CLASS__, __METHOD__, __LINE__, 'Failed loading token', [$provider]);

    $isUserCreated = false;
    if ($Controller->loginWithOAuth($accessToken, $isUserCreated)) {
      $Controller->e202_Accepted();
    }
    return false;
  }

  public function getFromMatches(string $key): ?string
  {
    return array_key_exists($key, $this->matchedGroups) ? $this->matchedGroups[$key] : null;
  }

  public function getFromPayload(string $key): ?string
  {
    return array_key_exists($key, $this->postdata) ? $this->postdata[$key] : null;
  }

  /**
   * This function returns the ERequestMethod enumeration value for the current
   * HTTP request method.
   * @return string with cosnt values from Surcouf\Cookbook\Request\ERequestMethod
   */
  private function getHttpRequestMethod(): ?string
  {
    switch ($_SERVER['REQUEST_METHOD']) {
      case 'GET':
        return ERequestMethod::HTTP_GET;
      case 'POST':
        return ERequestMethod::HTTP_POST;
      case 'PUT':
        return ERequestMethod::HTTP_PUT;
      case 'HEAD':
        return ERequestMethod::HTTP_HEAD;
      case 'DELETE':
        return ERequestMethod::HTTP_DELETE;
      case 'PATCH':
        return ERequestMethod::HTTP_PATCH;
      case 'OPTIONS':
        return ERequestMethod::HTTP_OPTIONS;
    }
    return ERequestMethod::Unknown;
  }

  public function getMatches(): array
  {
    return $this->matchedGroups;
  }

}