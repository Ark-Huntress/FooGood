<?php

// # utiliser des espaces de noms pour la requête HTTP
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// # include le framework Slim 
require __DIR__ . '/../vendor/autoload.php';


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

$app->run();