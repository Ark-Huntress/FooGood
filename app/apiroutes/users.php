<?php

require_once __DIR__ . '/../db/DBConnection.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/** 
 * Get Me (Connected User) (see documentation) 
 */ 
$app->get('/api/users/me', function( Request $request, Response $response) {
    $token = get_token_infos($request);

    try {
        $dbconn = new DB\DBConnection();
        $db = $dbconn->connect();    
    
        // query
        $sql = "SELECT * FROM users WHERE (username='" . $token->username . "')";
        $stmt = $db->query( $sql );
        $user = $stmt->fetchAll( PDO::FETCH_OBJ )[0];
        $db = null; // clear db object
    }
    catch( PDOException $e ) { 
        echo $e; 
        // response : 500 : PDO Error (DB) 
        $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}'); 
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500); 
    }

    $response->getBody()->write(json_encode($user));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

/** 
 * Modify Me (Connected User) - (see documentation) 
 */ 
$app->put('/api/users/me', function( Request $request, Response $response) {
    $token = get_token_infos($request);

    $dbconn = new DB\DBConnection();

    // Check user exists in database
    try {
        $db = $dbconn->connect();    
    
        // query
        $sql = "SELECT * FROM users WHERE (username='" . $token->username . "')";
        $stmt = $db->query( $sql );
        $users = $stmt->fetchAll( PDO::FETCH_OBJ );
        $db = null; // clear db object

        // Check if the user does not exist
        if (sizeof($users) != 1) {
            // response : 404 : not Found
            $response->getBody()->write('{"error": {"msg": "Could not find user."}}'); 
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404); 
        }

        $user = $users[0];
    }
    catch( PDOException $e ) { 
        echo $e; 
        // response : 500 : PDO Error (DB) 
        $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}'); 
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500); 
    }

    // The user exists

    // Prevent the user from modyfing their id or username
    if (array_key_exists("id", $request->getParsedBody()) || array_key_exists("username", $request->getParsedBody())) {
        // response : 400 : Bad Request
        $response->getBody()->write('{"error": {"msg": "Cannot modify ID or Username"}}'); 
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400); 
    }

    // Prevent having an empty array
    if (sizeof($request->getParsedBody()) == 0) {
        // response : 400 : Bad Request
        $response->getBody()->write('{"error": {"msg": "No changes were requested"}}'); 
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400); 
    }

    // On forge la requÃªte sql
    $sql = "UPDATE users SET";
    if (array_key_exists("first_name", $request->getParsedBody())) {
        $sql = $sql . " first_name='" . $request->getParsedBody()["first_name"] . "',"; //FIXME: les virgules ou les espaces?
    }
    if (array_key_exists("last_name", $request->getParsedBody())) {
        $sql = $sql . " last_name='" . $request->getParsedBody()["last_name"] . "'";
    }
    if (array_key_exists("password", $request->getParsedBody())) {
        $sql = $sql . " password='" . $request->getParsedBody()["password"] . "'";
    }
    $sql = $sql . " WHERE (id='" . $user->id . "')";

    // Apply the change
    try {
        echo $sql; //FIXME: get rid of this when the concatenation for the sql query is fixeds
        $db = $dbconn->connect();
        $stmt = $db->query( $sql );
        $db = null; // clear db object
    }
    catch( PDOException $e ) { 
        echo $e; 
        // response : 500 : PDO Error (DB) 
        $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}'); 
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500); 
    }

    // response : 200 : OK
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

/** 
 * Get All Users : admin only (see documentation) 
 */ 
$app->get('/api/users', function( Request $request, Response $response){ 
    //prepare query 
    $sql = "SELECT * FROM users ORDER BY id"; 

    try { 
        //check auth 
        $userdata = get_token_infos($request); 

        // check auth user is admin     
        if (!$userdata->admin) { 
            throw new Auth\UnauthorizedException("Service only available for admin user !"); 
        } 

        try { 
            //connect to DB 
            $dbconn = new DB\DBConnection(); 
            $db = $dbconn->connect();     

            // execute sql 
            $stmt = $db->query( $sql ); 
            $users = $stmt->fetchAll( PDO::FETCH_OBJ ); 
            $db = null; // clear db object  

            //response : 200 : Return All Users Array 
            $response->getBody()->write(json_encode( $users )); 
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200); 
        } catch( PDOException $e ) { 
            // response : 500 : PDO Error (DB) 
            $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}'); 
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500); 
        } 
    } 
    catch (Auth\UnauthenticatedException $e) { 
        //response : 401 : catch UnauthenticatedException : Authentication Error 
        $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}'); 
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401); 
    } 
    catch (Auth\UnauthorizedException $e) { 
        //response : 403 : catch UnauthorizedException : User Rights Access Denied Error 
        $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}'); 
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403); 
    } 
    catch (Exception $e) { 
        // Response 500 : Error 
        $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}'); 
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500); 
    } 
});

/** 
 * Function which parse token, decode user infos from this token and Throws  
 * UnauthenticatedException if Authentication Issue. 
 *  
 * The UnauthenticatedException must be catched in the caller and should result  
 * to a 401 Http Error 
 */ 
function get_token_infos(Request $request){ 
    if ($request->hasHeader('Authorization')) { 
        list($token) = sscanf($request->getHeaderLine('Authorization'), 'Bearer %s'); 
 
        $jwt = new Auth\JwtHandler(); 

        $data = $jwt->_jwt_decode_data($token); 
 
        return $data; 
    } 
    else{ 
        throw new Auth\UnauthenticatedException("Unable to find Authorization Header"); 
    } 
}