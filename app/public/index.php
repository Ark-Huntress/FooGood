<?php

// # utiliser des espaces de noms pour la requête HTTP
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// # include le framework Slim 
require __DIR__ . '/../vendor/autoload.php';

// Report simple running errors
error_reporting(E_ERROR | E_PARSE);

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

// # créer une nouvelle instance Slim
$app = new \Slim\App(
  [
    'settings' => $config, 
    'upload_directory' => __DIR__ . '/uploads' 
  ]
);


$app->get('/', function (Request $request, Response $response, $args) {
  $response->getBody()->write("Welcome in FooGood !");
  return $response;
});


// # include Users route
require __DIR__ . '/../apiroutes/users.php';
// # include Login route
require __DIR__ . '/../apiroutes/auth.php';
// # include OpenFoodFacts public route
require __DIR__ . '/../apiroutes/openfoodfacts.php';
// # include Shoppinglists public route
require __DIR__ . '/../apiroutes/shoppinglists.php';

//add middleware to add header for all routes
$app->add(function ($req, $res, $next) {
  $response = $next($req, $res);
  return $response
  ->withHeader('Access-Control-Allow-Origin', '*')
  ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
  ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
  });

$app->run();