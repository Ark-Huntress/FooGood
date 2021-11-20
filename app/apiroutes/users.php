<?php

require_once __DIR__ . '/../db/DBConnection.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;



$app->get('/api/users', function( Request $request, Response $response){
    
    $sql = "select * from users order by id";

    try
    {

        

        try {
            $dbconn = new DB\DBConnection();
            $db = $dbconn->connect();    
    
            // query
            $stmt = $db->query( $sql );
            $users = $stmt->fetchAll( PDO::FETCH_OBJ );
            $db = null; // clear db object
    
            // print out the result as json format
            //echo json_encode( $books );    
            
            $response->getBody()->write(json_encode( $users ));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch( PDOException $e ) {
            // show error message as Json format
            //echo '{"error": {"msg": ' . $e->getMessage() . '}';
            $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);

        }
    }
    catch (Exception $e)
    {
        $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
 
    
});