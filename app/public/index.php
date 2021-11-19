<?php

// # use Namespaces for HTTP request
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// # include the Slim framework
require __DIR__ . '/../vendor/autoload.php';


$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

// # create new Slim instance
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