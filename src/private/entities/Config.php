<?php

namespace Surcouf\Cookbook;

use \DateInterval;
use Surcouf\Cookbook\Config\DatabaseManagerInterface;
use Symfony\Component\Yaml\Yaml;

if (!defined('CORE2'))
  exit;

final class Config
{

  public const CTYPE_DBCREDENTIALS = 1;
  public const CTYPE_MAILCREDENTIALS = 2;
  public const CTYPE_OAUTHCREDENTIALS = 3;

  private $config;

  public function __construct()
  {
    if (!\file_exists('/config/config.yml'))
      throw new \Exception("/config/config.yml not found in folder config. Please check cbconfig.yml.template for more information.", 1);

    $this->config = Yaml::parse(file_get_contents('/config/config.yml'));
    $this->config['System']['MaintenanceMode'] = file_exists(ROOT . DS . '.maintenance.tmp');
    if (!defined('MAINTENANCE'))
      define('MAINTENANCE', $this->config['System']['MaintenanceMode']);
    if ($this->config['System']['DebugMode'] === true) {
      error_reporting(E_ALL);
      ini_set('display_errors', 'On');
    }
  }

  public function __call(string $methodName, array $params)
  {
    if (!array_key_exists($methodName, $this->config) || count($params) == 0)
      return null;
    $obj = $this->config[$methodName];
    for ($i = 0; $i < count($params); $i++) {
      if (!array_key_exists($params[$i], $obj) || $params[$i] == 'Credentials')
        return null;
      $obj = $obj[$params[$i]];
    }
    return $obj;
  }

  public function getCredentials(object $obj, int $type): bool
  {
    if ($type == self::CTYPE_DBCREDENTIALS && is_a($obj, DatabaseManagerInterface::class)) {
      $obj->setDatabaseHost($this->config['System']['Database']['Host'])
        ->setDatabasePort($this->config['System']['Database']['Port'])
        ->setDatabaseUser($this->config['System']['Database']['Credentials']['Name'])
        ->setDatabasePassword($this->config['System']['Database']['Credentials']['Password'])
        ->setDatabaseDbName($this->config['System']['Database']['Database']);
      return true;
    }
    return false;
  }

  public function getResponseArray(int $responseCode): array
  {
    return array_key_exists($responseCode, $this->responses) ? $this->responses[$responseCode] : $this->responses[10];
  }

}