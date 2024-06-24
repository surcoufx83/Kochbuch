<?php

namespace Surcouf\Cookbook;

use Exception;

spl_autoload_register(function ($className) {
  $className = str_replace(__NAMESPACE__ . '\\', '', $className);
  $file = '/var/www/html/private/entities/' . str_replace('\\', '/', $className) . '.php';
  if (file_exists($file))
    include_once ($file);
  else {
    var_dump($file);
    var_dump(file_exists($file));
    global $Controller;
    $Controller->e500_ServerError(null, 'spl_autoload_register', __LINE__, 'File to include not found', [$className, $file]);
  }
});

require_once '/var/www/html/private/backend/core.php';

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