<?php

require_once __DIR__ . '/../db/DBConnection.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
* Function which parse token, decode user infos from this token and Throws
* UnauthenticatedException if Authentication Issue. *
* The UnauthenticatedException must be catched in the caller and should result
* to a 401 Http Error */
function get_token_infos(Request $request){
    if ($request->hasHeader('Authorization')) {
        list($token) = sscanf($request->getHeaderLine('Authorization'), 'Bearer %s');
        
        $jwt = new Auth\JwtHandler();
        try
        {
            $data = $jwt->_jwt_decode_data($token);
            return $data;
        }
        catch (Exception $e)
        {
            throw new Auth\UnauthenticatedException("Invalid token : ". $e->getMessage());
        }
    }
    else{
        throw new Auth\UnauthenticatedException("Unable to find Authorization Header");
    }
}

$app->get('/api/users/me', function(Request $request, Response $response) {
    $parsedToken = get_token_infos($request);
    $response->getBody()->write(json_encode($parsedToken));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});



/**
 * Modify me (Connected User)
 */
$app->put('/api/users/me', function(Request $request, Response $response) {
    //Parser le token pour récupérer l'username
    $token = get_token_infos($request);

    //Vérifier que user existe dans la BDD
    try
    {
        $dbconn = new DB\DBConnection();
        $db = $dbconn->connect();

        $sql = "SELECT * FROM users WHERE username = '" . $token->username . "'";
        $stmt = $db->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;

        if (sizeof($users) != 1) {
            $response->getBody()->write('{"error": {"msg": "Could not find user"}}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $user = $users[0];

    } 
    catch( PDOException $e ) {
        // response : 500 : PDO Error (DB)
        $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // Si l'utilisateur existe (Erreur 400)
    if(array_key_exists("id", $request->getParsedBody()) || (array_key_exists("username", $request->getParsedBody()))){
        $response->getBody()->write('{"error": {"msg": "ID or Username not valid"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    } 

    // Si au moins un des 3 champs a été passé, Effectuer la requête Update
    //first_name
    $db = $dbconn->connect();
    if (array_key_exists("first_name", $request->getParsedBody())){
       $f_n = $request->getParsedBody()['first_name'];
       $sql = "UPDATE users SET `first_name` = $f_n WHERE id = " . $user->id;
       $stmt = $db->query($sql);
    }

    //last_name
    if (array_key_exists("last_name", $request->getParsedBody())){
        $l_n = $request->getParsedBody()['last_name'];
        $sql = "UPDATE users SET `last_name` = $l_n WHERE id = " . $user->id;
        $stmt = $db->query($sql);
    }

    //password
    if (array_key_exists("password", $request->getParsedBody())){
        $pswd = $request->getParsedBody()['password'];
        $sql = "UPDATE users SET `passsword` = $pswd WHERE id = " . $user->id;
        $stmt = $db->query($sql);
    }

    $db = null;
    // Réponse code
    $response->getBody()->write('{"msg": "OK"}');
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

/** --------------------------------------------------------------------------------- */
/**
 * Add User : admin only
 */
$app->post('/api/users', function(Request $request, Response $response){
    $user = get_token_infos($request);

    // Vérifie si user = admin
    if (!$user->admin) {
        $response->getBody()->write('{"error": {"msg": "Access Denied: admin mode only}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    // Si l'utilisateur existe (Erreur 400)
    if(array_key_exists("id", $request->getParsedBody())){
        $response->getBody()->write('{"error": {"msg": "ID or Username not valid"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    } 
    
    // Si les 4 param sont passes -> Create current time
    if(array_key_exists("username", $request->getParsedBody()) && (array_key_exists("first_name", $request->getParsedBody()) && (array_key_exists("last_name", $request->getParsedBody()) && (array_key_exists("password", $request->getParsedBody()))))){
        $u_n = $request->getParsedBody()['username'];
        $f_n = $request->getParsedBody()['first_name'];
        $l_n = $request->getParsedBody()['last_name'];
        $pswd = $request->getParsedBody()['password'];

        $sql = "INSERT INTO users (username, first_name, last_name, password) VALUES ($u_n, $f_n, $l_n, $pswd)";
        try {
            //connect to DB
            $dbconn = new DB\DBConnection();
            $db = $dbconn->connect();
            // execute sql
            $stmt = $db->query( $sql );
            $users = $stmt->fetchAll( PDO::FETCH_OBJ );
            $db = null; // clear db object

            $response->getBody()->write('{"msg": "Ajouté"}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch( PDOException $e ) {
            // response : 500 : PDO Error (DB)
            $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
        
    $db = null;

});
/** --------------------------------------------------------------------------------- */

/**
* Get User : admin only (see documentation) */
$app->get('/api/users/{username}', function( Request $request, Response $response){
    $user = get_token_infos($request);
    $username = $request->getAttribute('username');

    // Vérifie si user = admin
    if ($user->admin) {
        $dbconn = new DB\DBConnection();
        $db = $dbconn->connect();

        $sql = "SELECT * FROM users WHERE username = '" . $username . "'";
        $stmt = $db->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;

        if (sizeof($users) == 1) {
            $response->getBody()->write(json_encode($users));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

        $user = $users[0];
    } else {
        $response->getBody()->write('{"error": {"msg": "Access Denied: admin mode only}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }
    
});

/** --------------------------------------------------------------------------------- */

/**
* Modify User : admin only (see documentation) 
*/
$app->put('/api/users/{username}', function( Request $request, Response $response){
    //Parser le token pour récupérer l'username
    $token = get_token_infos($request);
    $username = $request->getAttribute('username');

    //Vérifier que user existe dans la BDD
    if($token->admin) {
        try
        {
            $dbconn = new DB\DBConnection();
            $db = $dbconn->connect();
    
            $sql = "SELECT * FROM users WHERE username = '" . $token->username . "'";
            $stmt = $db->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_OBJ);
            $db = null;
    
            if (sizeof($users) != 1) {
                $response->getBody()->write('{"error": {"msg": "Could not find user"}}');
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
    
            $user = $users[0];
    
        } 
        catch( PDOException $e ) {
            // response : 500 : PDO Error (DB)
            $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
   
        // Si l'utilisateur existe (Erreur 400)
        if(array_key_exists("id", $request->getParsedBody())){
            $response->getBody()->write('{"error": {"msg": "ID not valid"}}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        } 

        // Si au moins un des 3 champs a été passé, Effectuer la requête Update
        //first_name
        $db = $dbconn->connect();
        if (array_key_exists("first_name", $request->getParsedBody())){
        $f_n = $request->getParsedBody()['first_name'];
        $sql = "UPDATE users SET `first_name` = $f_n WHERE id = " . $user->id;
        $stmt = $db->query($sql);
        }

        //last_name
        if (array_key_exists("last_name", $request->getParsedBody())){
            $l_n = $request->getParsedBody()['last_name'];
            $sql = "UPDATE users SET `last_name` = $l_n WHERE id = " . $user->id;
            $stmt = $db->query($sql);
        }

        //password
        if (array_key_exists("password", $request->getParsedBody())){
            $pswd = $request->getParsedBody()['password'];
            $sql = "UPDATE users SET passsword = $pswd WHERE id = " . $user->id;
            $stmt = $db->query($sql);
        }
}

    $db = null;
    // Réponse code
    $response->getBody()->write('{"msg": "OK"}');
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

/** --------------------------------------------------------------------------------- */

/**
* Delete User : admin only (see documentation) 
*/
$app->delete('/api/users/{username}', function( Request $request, Response $response){
    $token = get_token_infos($request);
    $username = $request->getAttribute('username');

    if ($token->admin){
        //Vérifier que user existe dans la BDD
        try
        {
            $dbconn = new DB\DBConnection();
            $db = $dbconn->connect();

            $sql = "SELECT * FROM users WHERE username = '" . $token->username . "'";
            $stmt = $db->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_OBJ);

            if (sizeof($users) != 1) {
                $response->getBody()->write('{"error": {"msg": "Could not find user"}}');
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $sql = "DELETE FROM users WHERE username = '$username'";
            $stmt = $db->query($sql);
            
            $response->getBody()->write('{"msg": "OK"}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } 
        catch( PDOException $e ) {
            // response : 500 : PDO Error (DB)
            $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }


        $db = null;
  
    }
     
});
/** --------------------------------------------------------------------------------- */

$app->get('/api/users', function(Request $request, Response $response) {
    
    $sql = "SELECT * from users ORDER BY id";

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