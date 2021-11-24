<?php 
 
require_once __DIR__ . '/../auth/JwtHandler.php'; 
require_once __DIR__ . '/../db/DBConnection.php'; 
 
use Psr\Http\Message\ServerRequestInterface as Request; 
use Psr\Http\Message\ResponseInterface as Response; 
 
/** 
 * Connexion après la publication
 */ 
$app->post('/api/login', function( Request $request, Response $response){
    if ($request->getParsedBody() == null) {
        // response : 400 : Bad Request
        $response->getBody()->write('{"error": {"msg": "Please provide a username and a password."}}'); 
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400); 
    }

    // On récupère les identifiants utilisateurs
    $username = $request->getParsedBody()["name"];
    $password = $request->getParsedBody()["password"];

    $user = null; // utilisateur connecté

    // On récupère s'assure que l'utilisateur existe, et on récupère ses données
    try {
        $dbconn = new DB\DBConnection();
        $db = $dbconn->connect();    
    
        // query
        $sql = "SELECT * FROM users WHERE (username='" . $username . "' AND password='" . $password . "')";
        $stmt = $db->query( $sql );
        $users = $stmt->fetchAll( PDO::FETCH_OBJ );
        $db = null; // effacer l'objet de base de données
        
        if (sizeof($users) != 1) { // Aucun utilisateur trouvé ou trop, c'est-à-dire que les informations d'identification sont erronées
            // response : 403 : Forbidden
            $response->getBody()->write('{"error": {"msg": "Invalid credentials."}}'); 
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403); 
        }

        $user = $users[0];
    } 
    catch( PDOException $e ) { 
        echo $e; 
        // response : 500 : PDO Error (DB) 
        $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}'); 
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500); 
    }

    // Double-check que l'utilisateur est non-null
    if ($user == null) {
        // response : 403 : Forbidden
        $response->getBody()->write('{"error": {"msg": "Invalid credentials (user does not exist?)."}}'); 
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403); 
    }

    $jwt = new Auth\JwtHandler();
    $data = array(
        "username" => $username,
        "first_name" => $user->first_name,
        "last_name" => $user->last_name,
        "admin" => boolval($user->admin)
    );

    // Création du token
    $token = $jwt->_jwt_encode_data("FooGood.issuer", $data);

    // Envoi du token avec HTTP 200
    $response->getBody()->write(json_encode( $token ));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});