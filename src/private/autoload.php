<?php

namespace Surcouf\Cookbook;

use Exception;

spl_autoload_register(function ($className) {
  $className = str_replace(__NAMESPACE__ . '\\', '', $className);
  $file = DIR_ENTITIES . '/' . str_replace('\\', DS, $className) . '.php';
  if (file_exists($file))
    include_once($file);
});

require_once DIR_BACKEND . '/core.php';

$Controller = new Controller();
$Controller->init();

if (!ISCONSOLE) {
  try {
    if (Controller\RoutingManager::registerRoutes()) {
      $Controller->dispatch();
      exit;
    }
    $Controller->e501_NotImplemented(__FILE__, '', __LINE__, 'No matching endpoint', []);
  } catch (Exception $ex) {
    $Controller->e500_ServerError(__FILE__, '', __LINE__, $ex->getMessage(), []);
  }
} else {
  $Controller->Cli()->handle($argv);
}