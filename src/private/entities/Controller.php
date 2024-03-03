<?php

namespace Surcouf\Cookbook;

use Exception;
use mysqli;
use mysqli_stmt;
use Orhanerday\OpenAi\OpenAi;
use Surcouf\Cookbook\Config;
use Surcouf\Cookbook\Config\DatabaseManagerInterface;
use Surcouf\Cookbook\Controller\Dispatcher;
use Surcouf\Cookbook\Database\EAggregationType;
use Surcouf\Cookbook\Database\EQueryType;
use Surcouf\Cookbook\Database\QueryBuilder;
use Surcouf\Cookbook\Helper\DatabaseHelper;
use Surcouf\Cookbook\Helper\HashHelper;
use Surcouf\Cookbook\User\OAuthUser;
use Surcouf\Cookbook\User\User;
use Bahuma\OAuth2\Client\Provider\Nextcloud;
use \League\OAuth2\Client\Token\AccessToken;

if (!defined('CORE2'))
  exit;

final class Controller implements DatabaseManagerInterface
{

  private array $response = [];

  private $database, $currentUser;
  private $dispatcher;
  private $linkProvider;
  private $ObjectManager;
  private Cli $Cli;

  private $pictures = array();
  private $ratings = array();
  private $steps = array();
  private $tags = array();
  private $units = array();
  private $users = array();

  private string $apikey = '';

  private $changedObjects = array();
  private $dbIntransactionMode = false;

  private $transactionFiles = [];

  private $allowedUploadImageFiletypes = [
    'image/jpeg',
    'image/png',
  ];

  private $debugSessionCode = null;

  private string $dbhost, $dbuser, $dbpwd, $dbname;
  private int $dbport;

  public function __construct()
  {
  }

  public function Cli(): ?Cli
  {
    return $this->Cli;
  }

  public function Config(): Config
  {
    global $Config;
    return $Config;
  }

  public function Dispatcher(): Dispatcher
  {
    return $this->dispatcher;
  }

  public function User(): ?User
  {
    return $this->currentUser;
  }

  public function addFileToTransaction(string $filepath)
  {
    $this->transactionFiles[] = $filepath;
  }

  public function ai(array $inputMessages): bool|string
  {
    $chatinput = [
      'model' => 'gpt-4-vision-preview',
      'max_tokens' => 4000,
      'messages' => $inputMessages
    ];
    $open_ai = new OpenAi($this->apikey);
    return $open_ai->chat(json_decode(json_encode($chatinput), true));
  }

  public function aiAvailable(): bool
  {
    return $this->apikey !== '';
  }

  public function cancelTransaction(): bool
  {
    if ($this->dbIntransactionMode) {
      $ret = $this->database->rollback();
      $this->database->autocommit(true);
      $this->dbIntransactionMode = false;
      for ($i = 0; $i < $this->transactionFiles; $i++) {
        @unlink($this->transactionFiles[$i]);
      }
      return $ret;
    }
    return true;
  }

  public function dberror(): string
  {
    return $this->database->error;
  }

  public function dberror2(): array
  {
    return [$this->database->errno, $this->database->error];
  }

  /** todo: move to query builder */
  public function dbescape($value, string $separator = ', '): string
  {
    if (is_null($value))
      return 'NULL';
    if (is_integer($value) || is_float($value))
      return $value;
    if (is_bool($value))
      return intval($value);
    if (is_array($value)) {
      for ($i = 0; $i < count($value); $i++)
        $value[$i] = $this->dbescape($value[$i], $separator);
      return join($separator, $value);
    }
    $value = '\'' . $this->database->real_escape_string($value) . '\'';
    return $value;
  }

  public function defaultLocale(): string
  {
    return 'de';
  }

  public function delete(QueryBuilder &$qbuilder): bool
  {
    $query = $qbuilder->buildQuery();
    $result = $this->database->query($query);
    return $result;
  }

  public function dispatch(): void
  {
    $this->Dispatcher()->dispatchRoute($this->response);
  }

  /**
   * Send the response to the client based on the given status code in param $code.
   * Optional error can be tracked. This is a shorthand for the exxx_ methods.
   *
   * @param integer $code The status code that must also be available as exxx_ method
   * @param string|null $classname For logging: the class name
   * @param string|null $methodname For logging: the method name
   * @param integer|null $lineno For logging: the line number
   * @param string|null $message For logging: the message
   * @param $payload For logging: the any data you want to log
   * @return void
   */
  public function e(int $code, string $classname = null, string $methodname = null, int $lineno = null, string $message = null, $payload = null): void
  {
    switch ($code) {
      case 200:
        $this->e200_Ok($classname, $methodname, $lineno, $message, $payload);
      case 201:
        $this->e201_Created($classname, $methodname, $lineno, $message, $payload);
      case 202:
        $this->e202_Accepted($classname, $methodname, $lineno, $message, $payload);
      case 204:
        $this->e204_NoContent($classname, $methodname, $lineno, $message, $payload);
      case 304:
        $this->e304_NotModified($classname, $methodname, $lineno, $message, $payload);
      case 400:
        $this->e400_BadRequest($classname, $methodname, $lineno, $message, $payload);
      case 401:
        $this->e401_Unauthorized($classname, $methodname, $lineno, $message, $payload);
      case 403:
        $this->e403_Forbidden($classname, $methodname, $lineno, $message, $payload);
      case 404:
        $this->e404_NotFound($classname, $methodname, $lineno, $message, $payload);
      case 500:
        $this->e500_ServerError($classname, $methodname, $lineno, $message, $payload);
      default:
        $this->e200_Ok($classname, $methodname, $lineno, $message, $payload);
    }
  }

  public function e200_Ok(string $classname = null, string $methodname = null, int $lineno = null, string $message = null, $payload = null): void
  {
    if (!is_null($classname) || !is_null($methodname) || !is_null($lineno) || !is_null($message) || !is_null($payload))
      $this->loge($classname, $methodname, $lineno, $message, $payload);
    http_response_code(200);
    echo json_encode($this->response);
    $this->dispatcher->dispatchToCache($this->response);
    $this->logi(__CLASS__, __METHOD__, __LINE__, 'Output sent to browser.', [$this->debugSessionCode, 200, $this->response]);
    exit;
  }

  public function e201_Created(string $classname = null, string $methodname = null, int $lineno = null, string $message = null, $payload = null): void
  {
    if (!is_null($classname) || !is_null($methodname) || !is_null($lineno) || !is_null($message) || !is_null($payload))
      $this->loge($classname, $methodname, $lineno, $message, $payload);
    http_response_code(201);
    echo json_encode($this->response);
    $this->logi(__CLASS__, __METHOD__, __LINE__, 'Output sent to browser.', [$this->debugSessionCode, 201, $this->response]);
    exit;
  }

  public function e202_Accepted(string $classname = null, string $methodname = null, int $lineno = null, string $message = null, $payload = null): void
  {
    if (!is_null($classname) || !is_null($methodname) || !is_null($lineno) || !is_null($message) || !is_null($payload))
      $this->loge($classname, $methodname, $lineno, $message, $payload);
    http_response_code(202);
    echo json_encode($this->response);
    $this->logi(__CLASS__, __METHOD__, __LINE__, 'Output sent to browser.', [$this->debugSessionCode, 202, $this->response]);
    exit;
  }

  public function e204_NoContent(string $classname = null, string $methodname = null, int $lineno = null, string $message = null, $payload = null): void
  {
    if (!is_null($classname) || !is_null($methodname) || !is_null($lineno) || !is_null($message) || !is_null($payload))
      $this->loge($classname, $methodname, $lineno, $message, $payload);
    http_response_code(204);
    echo json_encode($this->response);
    $this->logi(__CLASS__, __METHOD__, __LINE__, 'Output sent to browser.', [$this->debugSessionCode, 204, $this->response]);
    exit;
  }

  public function e304_NotModified(string $classname = null, string $methodname = null, int $lineno = null, string $message = null, $payload = null): void
  {
    if (!is_null($classname) || !is_null($methodname) || !is_null($lineno) || !is_null($message) || !is_null($payload))
      $this->loge($classname, $methodname, $lineno, $message, $payload);
    http_response_code(304);
    echo json_encode($this->response);
    $this->logi(__CLASS__, __METHOD__, __LINE__, 'Output sent to browser.', [$this->debugSessionCode, 304, $this->response]);
    exit;
  }

  public function e400_BadRequest(string $classname = null, string $methodname = null, int $lineno = null, string $message = null, $payload = null): void
  {
    if (!is_null($classname) || !is_null($methodname) || !is_null($lineno) || !is_null($message) || !is_null($payload))
      $this->loge($classname, $methodname, $lineno, $message, $payload);
    http_response_code(400);
    echo json_encode($this->response);
    $this->logi(__CLASS__, __METHOD__, __LINE__, 'Output sent to browser.', [$this->debugSessionCode, 400, $this->response]);
    exit;
  }

  public function e401_Unauthorized(string $classname = null, string $methodname = null, int $lineno = null, string $message = null, $payload = null): void
  {
    if (!is_null($classname) || !is_null($methodname) || !is_null($lineno) || !is_null($message) || !is_null($payload))
      $this->loge($classname, $methodname, $lineno, $message, $payload);
    http_response_code(401);
    echo json_encode($this->response);
    $this->logi(__CLASS__, __METHOD__, __LINE__, 'Output sent to browser.', [$this->debugSessionCode, 401, $this->response]);
    exit;
  }

  public function e403_Forbidden(string $classname = null, string $methodname = null, int $lineno = null, string $message = null, $payload = null): void
  {
    if (!is_null($classname) || !is_null($methodname) || !is_null($lineno) || !is_null($message) || !is_null($payload))
      $this->loge($classname, $methodname, $lineno, $message, $payload);
    http_response_code(403);
    echo json_encode($this->response);
    $this->logi(__CLASS__, __METHOD__, __LINE__, 'Output sent to browser.', [$this->debugSessionCode, 404, $this->response]);
    exit;
  }

  public function e404_NotFound(string $classname = null, string $methodname = null, int $lineno = null, string $message = null, $payload = null): void
  {
    if (!is_null($classname) || !is_null($methodname) || !is_null($lineno) || !is_null($message) || !is_null($payload))
      $this->loge($classname, $methodname, $lineno, $message, $payload);
    http_response_code(404);
    echo json_encode($this->response);
    $this->logi(__CLASS__, __METHOD__, __LINE__, 'Output sent to browser.', [$this->debugSessionCode, 404, $this->response]);
    exit;
  }

  public function e500_ServerError(string $classname = null, string $methodname = null, int $lineno = null, string $message = null, $payload = null): void
  {
    if (!is_null($classname) || !is_null($methodname) || !is_null($lineno) || !is_null($message) || !is_null($payload))
      $this->loge($classname, $methodname, $lineno, $message, $payload);
    http_response_code(500);
    echo json_encode($this->response);
    $this->logi(__CLASS__, __METHOD__, __LINE__, 'Output sent to browser.', [$this->debugSessionCode, 500, $this->response]);
    exit;
  }

  public function e501_NotImplemented(string $classname = null, string $methodname = null, int $lineno = null, string $message = null, $payload = null): void
  {
    if (!is_null($classname) || !is_null($methodname) || !is_null($lineno) || !is_null($message) || !is_null($payload))
      $this->loge($classname, $methodname, $lineno, $message, $payload);
    http_response_code(501);
    $this->logi(__CLASS__, __METHOD__, __LINE__, 'Output sent to browser.', [$this->debugSessionCode, 501, $this->response]);
    exit;
  }

  public function finishTransaction(): bool
  {
    $ret = $this->database->commit();
    if ($ret == false) {
      $this->database->rollback();
    }
    $this->database->autocommit(true);
    $this->dbIntransactionMode = false;
    return $ret;
  }

  public function get(array $params): void
  {
    $this->dispatcher->get($params);
  }

  public function getBrowserIdentifier(): string
  {
    return HashHelper::hash(
      sprintf('%s%s', $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR'])
    );
  }

  public function getInsertId(): ?int
  {
    return $this->database->insert_id;
  }

  public function getLink(string $filter, ...$args): ?string
  {
    $filter2 = str_replace(':', '_', $filter);
    return $this->linkProvider->$filter2($args);
  }

  public function getOAuthProvider(): Nextcloud
  {
    return new Nextcloud([
      'clientId' => $this->Config()->System('OAUTH2', 'ClientId'),
      'clientSecret' => $this->Config()->System('OAUTH2', 'ClientSecret'),
      'redirectUri' => $this->Config()->System('OAUTH2', 'RedirectUrl'),
      'nextcloudUrl' => 'https://cloud.mogul.network',
    ]);
  }

  public function init(): void
  {
    if (!$this->init_Config())
      exit('Error loading configuration');
    $this->debugSessionCode = HashHelper::hash((new \DateTime('now'))->format('c') . rand());
    if (!$this->init_Database())
      exit('Error loading database');
    if (!$this->init_Dispatcher())
      exit('Error loading dispatcher');
    if (ISCONSOLE)
      $this->Cli = new Cli();
  }

  private function init_Config(): bool
  {
    $this->Config()->initController();
    $this->apikey = $this->Config()->System('ChatGpt', 'ApiKey');
    return true;
  }

  private function init_Database(): bool
  {
    if (!$this->Config()->getCredentials($this, Config::CTYPE_DBCREDENTIALS)) {
      exit('Error loading database setup');
    }
    try {
      $this->database = new mysqli($this->dbhost, $this->dbuser, $this->dbpwd, $this->dbname, $this->dbport);
      if ($this->database->connect_errno != 0) {
        exit('Error connecting to the database.');
      }
      $this->database->set_charset('utf8mb4');
    } catch (Exception $e) {
      exit('Error connecting to the database.');
    }
    return true;
  }

  private function init_Dispatcher(): bool
  {
    $this->dispatcher = new Dispatcher();
    $this->login();
    return true;
  }

  public function insert(QueryBuilder &$qbuilder): bool
  {
    $query = $qbuilder->buildQuery();
    $result = $this->database->query($query);
    return $result;
  }

  public function insertSimple(string $table, array $columns, array $data): int
  {
    $query = new QueryBuilder(EQueryType::qtINSERT, $table);
    $query->columns($columns)
      ->values($data);
    if ($this->insert($query)) {
      $id = $this->getInsertId();
      return $this->getInsertId();
    }
    return -1;
  }

  public function isAuthenticated(): bool
  {
    return !is_null($this->currentUser);
  }

  public function isUploadAllowed(string $mimetype): bool
  {
    return in_array($mimetype, $this->allowedUploadImageFiletypes);
  }

  public function log(string $sev, string $classname, string $methodname, int $lineno, string $message, $payload = null): void
  {
    $stmt = $this->database->prepare('INSERT INTO apilog(severity, phpclass, phpmethod, phpline, message, payload, host, request_length, request_uri, request_type) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $payload = !is_null($payload) ? json_encode($payload) : '';
    $serverName = array_key_exists('SERVER_NAME', $_SERVER) && !is_null($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'cli';
    $contentLength = array_key_exists('CONTENT_LENGTH', $_SERVER) && !is_null($_SERVER['CONTENT_LENGTH']) ? $_SERVER['CONTENT_LENGTH'] : 0;
    $requestUri = array_key_exists('REQUEST_URI', $_SERVER) && !is_null($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $contentType = array_key_exists('CONTENT_TYPE', $_SERVER) && !is_null($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    $stmt->bind_param(
      'sssisssiss',
      $sev,
      $classname,
      $methodname,
      $lineno,
      $message,
      $payload,
      $serverName,
      $contentLength,
      $requestUri,
      $contentType
    );
    $stmt->execute();
  }

  public function loge(string $classname, string $methodname, int $lineno, string $message, $payload = null): void
  {
    $this->log('E', $classname, $methodname, $lineno, $message, $payload);
  }

  public function logf(string $classname, string $methodname, int $lineno, Exception $e, $payload = null): void
  {
    $this->log('F', $classname, $methodname, $lineno, $e->getMessage(), [$payload, $e->getTrace()]);
  }

  public function logi(string $classname, string $methodname, int $lineno, string $message, $payload = null): void
  {
    $this->log('I', $classname, $methodname, $lineno, $message, $payload);
  }

  public function logw(string $classname, string $methodname, int $lineno, string $message, $payload = null): void
  {
    $this->log('W', $classname, $methodname, $lineno, $message, $payload);
  }

  private function login(): bool
  {
    if (ISWEB)
      return $this->loginWithCookies();
    else
      return $this->loginCli();
  }

  private function loginCli(): bool
  {
    return true;
  }

  private function loginWithCookies(): bool
  {
    if (count($_COOKIE) == 0)
      return false;
    if (
      !array_key_exists($this->Config()->System('Cookies', 'UserCookieName'), $_COOKIE) ||
      !array_key_exists($this->Config()->System('Cookies', 'SessionCookieName'), $_COOKIE) ||
      !array_key_exists($this->Config()->System('Cookies', 'PasswordCookieName'), $_COOKIE)
    ) {
      return false;
    }
    $uname = $_COOKIE[$this->Config()->System('Cookies', 'UserCookieName')];
    $user = User::load($uname);
    if (is_null($user) || !$user->verifySession($_COOKIE[$this->Config()->System('Cookies', 'SessionCookieName')], $_COOKIE[$this->Config()->System('Cookies', 'PasswordCookieName')])) {
      $this->removeCookies();
      return false;
    }
    $this->currentUser = $user;
    $this->renewSession();
    return true;
  }

  public function loginWithOAuth(AccessToken $token, bool &$userCreated): bool
  {
    $values = $token->getValues();
    if (!array_key_exists('user_id', $values))
      return false;
    $userid = 'OAuth2::' . $values['user_id'] . '@' . $this->Config()->System('OAUTH2', 'DisplayName');
    $user = User::load($userid);
    if (is_null($user)) {
      $user = new OAuthUser($values['user_id']);
      $response = [];
      if (!$user->save($response))
        return false;
      $userCreated = true;
    }
    $this->currentUser = &$user;
    $this->currentUser->createNewSession(true, $token);
    return true;
  }

  /* public function loginWithPassword(string $email, string $password, bool $keepSession, array &$response = null): bool
  {
    if ($email == '' || $password == '') {
      $response = $this->Config()->getResponseArray(30);
      return false;
    }
    $user = $this->OM()->User($email);
    if (is_null($user) || !$user->verify($password)) {
      $response = $this->Config()->getResponseArray(30);
      return false;
    }
    $this->currentUser = &$user;
    $this->currentUser->createNewSession($keepSession);
    $response = $this->Config()->getResponseArray(31);
    return true;
  } */

  public function logout(): void
  {
    if ($this->isAuthenticated()) {
      $this->isAuthenticated = false;
      $this->currentUser->getSession()->destroy();
      $this->currentUser = null;
    }
    $this->removeCookies();
  }

  public function on(string $method, array $params): void
  {
    $this->dispatcher->on($method, $params);
  }

  public function post(array $params): void
  {
    $this->dispatcher->post($params);
  }

  public function prepare(string $query, bool $e500OnError = true)
  {
    $stmt = $this->database->prepare($query);
    if ($stmt === true || $stmt === false) {
      $this->loge(__CLASS__, __METHOD__, __LINE__, $this->database->error, [$query]);
      if ($e500OnError)
        $this->e500_ServerError();
      return null;
    }
    return $stmt;
  }

  public function put(array $params): void
  {
    $this->dispatcher->put($params);
  }

  /** todo: move to session or helper class */
  private function removeCookies(): void
  {
    $keys = array_keys($_COOKIE);
    for ($i = 0; $i < count($keys); $i++) {
      setcookie($keys[$i], null, -1);
      unset($_COOKIE[$keys[$i]]);
    }
  }

  /** todo: move to session or helper class */
  private function renewSession(): void
  {
    $session = $this->currentUser->getSession();
    $expires = 0;
    $this->setSessionCookies(
      $_COOKIE[$this->Config()->System('Cookies', 'UserCookieName')],
      $_COOKIE[$this->Config()->System('Cookies', 'SessionCookieName')],
      $_COOKIE[$this->Config()->System('Cookies', 'PasswordCookieName')],
      $session->keep()
    );
  }

  public function select(QueryBuilder &$queryBuilder): ?\mysqli_result
  {
    return DatabaseHelper::select($this->database, $queryBuilder);
  }

  public function selectCountSimple(string $table, string $filterColumn = null, string $filterValue = null): int
  {
    $query = new QueryBuilder(EQueryType::qtSELECT, $table);
    $query->select([['*', EAggregationType::atCOUNT, 'count']]);
    if (!is_null($filterColumn))
      $query->where($table, $filterColumn, '=', $filterValue);
    return $this->select($query)->fetch_assoc()['count'];
  }

  public function selectFirst(QueryBuilder &$queryBuilder): ?array
  {
    return DatabaseHelper::selectFirst($this->database, $queryBuilder);
  }

  public function selectObject(QueryBuilder &$queryBuilder, string $className): ?object
  {
    return DatabaseHelper::selectObject($this->database, $queryBuilder, $className);
  }

  /** todo: move to session or helper class */
  private function setCookie(string $name, string $value, int $expiration): bool
  {
    return setcookie($name, $value, $expiration, '/');
  }

  public function setDatabaseDbName(string $dbname): DatabaseManagerInterface
  {
    $this->dbname = $dbname;
    return $this;
  }

  public function setDatabaseHost(string $hostname): DatabaseManagerInterface
  {
    $this->dbhost = $hostname;
    return $this;
  }

  public function setDatabasePassword(string $password): DatabaseManagerInterface
  {
    $this->dbpwd = $password;
    return $this;
  }

  public function setDatabasePort(int $port): DatabaseManagerInterface
  {
    $this->dbport = $port;
    return $this;
  }

  public function setDatabaseUser(string $username): DatabaseManagerInterface
  {
    $this->dbuser = $username;
    return $this;
  }

  /** todo: move to session or helper class */
  public function setSessionCookies(string $userCookie, string $tokenCookie, string $passwordCookie, bool $longDuration): bool
  {
    $expires = 0;
    if ($longDuration) {
      $NOW = new \DateTime();
      $expdatetime = $NOW->add(new \DateInterval($this->Config()->Users('Sessions', 'LongExpiry')));
      $expires = $expdatetime->getTimestamp();
    }
    return ($this->setCookie($this->Config()->System('Cookies', 'UserCookieName'), $userCookie, $expires)
      && $this->setCookie($this->Config()->System('Cookies', 'SessionCookieName'), $tokenCookie, $expires)
      && $this->setCookie($this->Config()->System('Cookies', 'PasswordCookieName'), $passwordCookie, $expires));
  }

  public function startTransaction(): bool
  {
    $this->database->autocommit(false);
    $this->dbIntransactionMode = $this->database->begin_transaction();
    return true;
  }

  public function update(QueryBuilder &$qbuilder): bool
  {
    $query = $qbuilder->buildQuery();
    $result = $this->database->query($query);
    return $result;
  }

  public function userLocale(): string
  {
    return array_key_exists('l', $_GET) ? ($_GET['l'] == 'de' || $_GET['l'] == 'en' ? $_GET['l'] : $this->defaultLocale()) : $this->defaultLocale();
  }

  /* public function updateDbObject(DbObjectInterface &$object): void
  {
    $key = get_class($object) . $object->getId();
    if (!array_key_exists($key, $this->changedObjects))
      $this->changedObjects[$key] = $object;
  } */
}