<?php

namespace Surcouf\Cookbook\Controller;

use Surcouf\Cookbook\Controller\Routes\Api\Recipe\Picture\AiUploadRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\Picture\DeleteRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\Picture\UploadRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\RandomRecipePageRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\RecipeCategoriesRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\RecipeCreateRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\RecipeDeleteRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\RecipeEditRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\RecipeListRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\RecipePageRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\RecipePublishRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\RecipeReadingRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\RecipeRejectRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\Vote\DeleteVoteRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Recipe\Vote\SubmitVoteRoute;
use Surcouf\Cookbook\Controller\Routes\Api\Search\SearchRoute;
use Surcouf\Cookbook\Controller\Routes\Api\User\GetOwnProfileRoute;
use Surcouf\Cookbook\Controller\Routes\Api\User\LogoutRoute;
use Surcouf\Cookbook\Controller\Routes\User\OAuth2CallbackRoute2;
use Surcouf\Cookbook\Controller\Routes\User\OAuth2GetParamsRoute;
use Surcouf\Cookbook\Request\ERequestMethod;

if (!defined('CORE2'))
  exit;

final class RoutingManager
{

  static $routes = [

    '/api/categories(\?l=(?<langcode>[a-z]{2}(\-[A-Z]{2})?))?' => [
      // return a list of known categories (cached if not changed)
      'class' => RecipeCategoriesRoute::class,
      'method' => ERequestMethod::HTTP_GET,
      'requiresUser' => false,
      'cacheable' => true,
      'cacheByUser' => false,
    ],

    '/api/logout' => [
      // mark user as loggedout and remove session
      'class' => LogoutRoute::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => true,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/me(\?l=(?<langcode>[a-z]{2})(\-[A-Z]{2})?)?' => [
      // get user profile information
      'class' => GetOwnProfileRoute::class,
      'method' => ERequestMethod::HTTP_GET,
      'requiresUser' => true,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/oauth2/params(\?l=(?<langcode>[a-z]{2})(\-[A-Z]{2})?)?' => [
      // init oauth login
      'class' => OAuth2GetParamsRoute::class,
      'method' => ERequestMethod::HTTP_GET,
      'requiresUser' => false,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/oauth2/submit' => [
      // submit oauth login code and state
      'class' => OAuth2CallbackRoute2::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => false,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/random(\?skip=(?<filterid>\d+))?([?&]l=(?<langcode>[a-z]{2})(\-[A-Z]{2})?)?' => [
      // random recipe page
      'class' => RandomRecipePageRoute::class,
      'method' => ERequestMethod::HTTP_GET,
      'requiresUser' => false,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/recipe/(?<id>\d+)(\?l=(?<langcode>[a-z]{2})(\-[A-Z]{2})?)?' => [
      // recipe details
      'class' => RecipePageRoute::class,
      'method' => ERequestMethod::HTTP_GET,
      'requiresUser' => false,
      'cacheable' => true,
      'cacheByUser' => false,
    ],

    '/api/recipe/ai/scan(/(?<id>\d+))?' => [
      // upload a picture to convert it via AI into a recipe object
      'class' => AiUploadRoute::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => true,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/recipe/(?<id>\d+)/delete' => [
      // delete recipe
      'class' => RecipeDeleteRoute::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => true,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/recipe/(?<id>\d+)/edit' => [
      // edit recipe
      'class' => RecipeEditRoute::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => true,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/recipe/(?<id>\d+)/picture/add' => [
      // add picture to recipe
      'class' => UploadRoute::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => true,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/recipe/(?<recipeid>\d+)/picture/(?<pictureid>\d+)/delete' => [
      // delete picture from recipe
      'class' => DeleteRoute::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => true,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/recipe/(?<id>\d+)/publish/(?<target>private|internal|external)' => [
      // publish or reject recipe
      'class' => RecipePublishRoute::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => true,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/recipe/(?<id>\d+)/reading' => [
      // mark recipe as viewed
      'class' => RecipeReadingRoute::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => false,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/recipe/create' => [
      // create new recipe placeholder
      'class' => RecipeCreateRoute::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => true,
      'cacheable' => false,
      'cacheByUser' => false,
    ],

    '/api/recipes/(?<group>my|user|home)(\?(?<filter>[^?]+))?([?&]l=(?<langcode>[a-z]{2})(\-[A-Z]{2})?)?' => [
      // return a list of recipes (cached if not changed)
      // either for home, own or other users recipes
      'class' => RecipeListRoute::class,
      'method' => ERequestMethod::HTTP_GET,
      'requiresUser' => false,
      'cacheable' => true,
      'cacheByUser' => true,
    ],

    '/api/search?.+' => [
      // search for recipe
      'class' => SearchRoute::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => false,
      'cacheable' => true,
      'cacheByUser' => true,
    ],

    '/api/vote/(?<id>\d+)' => [
      // user submit his vote
      'class' => SubmitVoteRoute::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => true,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

    '/api/vote/(?<id>\d+)/delete' => [
      // delete users vote
      'class' => DeleteVoteRoute::class,
      'method' => ERequestMethod::HTTP_POST,
      'requiresUser' => true,
      'cacheable' => false,
      'cacheByUser' => true,
    ],

  ];

  static function registerRoutes(): bool
  {
    global $Controller;

    foreach (self::$routes as $key => $data) {
      if (array_key_exists('class', $data)) {
        if ($Controller->Dispatcher()->addRoute($key, $data))
          return true;
      } else {
        for ($i = 0; $i < count($data); $i++) {
          if ($Controller->Dispatcher()->addRoute($key, $data[$i]))
            return true;
        }
      }
    }

    return false;

  }

}