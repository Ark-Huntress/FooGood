<?php

require_once __DIR__ . '/../db/DBConnection.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Function to retrieve - from DB - all articles (short format) for a specified shoppinglist's id
 * It returns an array of articles in short format description (using get_product_data_array
 * function from 'openfoodfacts.php')
 * Note : if an article qtity > 1, this'll be generated several time in the returned array.
 */

 function get_articles($shoppinglistid){
     //connect to DB
     $dbconn = new DB\DBConnection();
     $db = $dbconn->connect();

     //prepare query
     $sql = "SELECT article_id, quantity FROM shoppinglists_articles WHERE shoppinglist_id = :shoppinglistid";

     // https://www.php.net/manual/en/pdo.prepare.php
     $stmt = $db->prepare($sql);

     //bind each param
     // https://www.php.net/manual/en/pdostatement.bindparam.php
     $stmt->bindParam(':shoppinglistid', $shoppinglistid);

     //execute sql
     $stmt->execute();
     $articles = $stmt->fetchAll(PDO::FETCH_OBJ);
     $db = null; // clear db object

     //create empty array
     $articles_res = array();

     //create OFFs api object
     $api = new OpenFoodFacts\Api('food', 'fr');

     //foreach article's row from 'shoppinglists_articles' table
     foreach($articles as $row){
         //retrieve article desc from api
         //convert it in short desc
         //add it into result array as many times as qtity
         $articleid = $row->article_id;
         $qty = intval($row->quantity);
         $product = $api->getProduct($articleid);
         $shortproduct = get_product_short_data_array($product->getData());

         if (!is_null($shortproduct))
         {
             for ($cpt=0; $cpt<$qty; $cpt++)
             {
                 $articles_res[] = $shortproduct;
             }
         }
     }
     return $articles_res;
}


$app->get('/api/shoppinglists', function(Request $request, Response $response) {
    $token = get_token_infos($request);

    $sl_result = array(); 
   try
   {
       $dbconn = new DB\DBConnection();
       $db = $dbconn->connect();

       $sql = "SELECT * FROM shoppinglists WHERE username = '" . $token->username . "'";
       $stmt = $db->query($sql);
       $shoppinglists = $stmt->fetchAll(PDO::FETCH_OBJ);

       foreach ($shoppinglists as $shoppinglist){
           $articles = get_articles($shoppinglist->id);
           $mean_nutriscore;

           foreach($articles as $article){
               if (!empty($mean_nutriscore)) {
                   $mean_nutriscore += $article["nutriscore"];
                }
                else {
                    $mean_nutriscore = $article["nutriscore"];
                }
                $shoppinglist->mean_nutriscore = $mean_nutriscore;
           }

           $sl_result[] = $shoppinglist;
           
       }
       $db = null;
       $response->getBody()->write(json_encode($sl_result));
       return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
   } catch (PDOException $e) {
       echo $e;
       //response : 500 : PDO Error (DB)
       $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
       return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
   }
});


$app->post('/api/shoppinglists', function(Request $request, Response $response) {
    $token = get_token_infos($request);

    try
    {
        $dbconn = new DB\DBConnection();
        $db = $dbconn->connect();

        $sql = "SELECT * FROM shoppinglists WHERE username = '" . $token->username . "'";
        $stmt = $db->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
       

        // Si l'utilisateur existe (Erreur 400)
        if(array_key_exists("id", $request->getParsedBody())){
            $response->getBody()->write('{"error": {"msg": "ID or Username not valid"}}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        } 
    } 
    catch( PDOException $e ) {
        // response : 500 : PDO Error (DB)
        $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    if((array_key_exists("title", $request->getParsedBody())) && (array_key_exists("purchase_date", $request->getParsedBody()))){
        $tt = $request->getParsedBody()['title'];
        $p_d = $request->getParsedBody()['purchase_date'];
        
        $sql = "INSERT INTO shoppinglists (`username`, `title`, `creation_date`, `purchase_date`) VALUES ('$token->username','$tt', now(), '$p_d')";
        $stmt = $db->query($sql);

        $response->getBody()->write('{"msg": "Ajouté"}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } 
    else {
        $response->getBody()->write('{"error": {"msg": "Mauvais paramètres passés"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $db = null;

});

$app->get('/api/shoppinglists/{shoppinglistid}', function(Request $request, Response $response){
    $token = get_token_infos($request);
    $sl_id = $request->getAttribute('shoppinglistid');

    try
   {
       $dbconn = new DB\DBConnection();
       $db = $dbconn->connect();

       $sql = "SELECT * FROM shoppinglists WHERE username = '" . $token->username . "'";
       $stmt = $db->query($sql);
       $shoppinglists = $stmt->fetchAll(PDO::FETCH_OBJ);

       if ($shoppinglists) {
           foreach ($shoppinglists as $shoppinglist){
               $articles = get_articles($shoppinglist->id);
               $mean_nutriscore;

            foreach($articles as $article)
            {
                if (!empty($mean_nutriscore)) {
                    $mean_nutriscore += $article["nutriscore"];
                }
                else {
                    $mean_nutriscore = $article["nutriscore"];
                }
                $shoppinglist->mean_nutriscore = $mean_nutriscore;
            }
       }
    }
       $db = null;
       $response->getBody()->write(json_encode($shoppinglist));
       return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
       
   } catch( PDOException $e ) {
       // response : 500 : PDO Error (DB)
       $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
       return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});


$app->put('/api/shoppinglists/{shoppinglistid}', function(Request $request, Response $response){
    $token = get_token_infos($request);
    $sl_id = $request->getAttribute('shoppinglistid');

    try
   {
       $dbconn = new DB\DBConnection();
       $db = $dbconn->connect();

       $sql = "SELECT * FROM shoppinglists WHERE username = '" . $token->username . "'";
       $stmt = $db->query($sql);
       $shoppinglists = $stmt->fetchAll(PDO::FETCH_OBJ);
       $db = null;
       
   } catch( PDOException $e ) {
       // response : 500 : PDO Error (DB)
       $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
       return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

      // Si l'utilisateur existe (Erreur 400)
    if(array_key_exists("id", $request->getParsedBody())){
        $response->getBody()->write('{"error": {"msg": "ID or Username not valid"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    } 

    $db = $dbconn->connect();
    if ($shoppinglists) {
        if (array_key_exists("title", $request->getParsedBody())){
            $title = $request->getParsedBody()['title'];
            $sql = "UPDATE shoppinglists SET `title` = '$title' WHERE `id` = '$sl_id'";
            $stmt = $db->query($sql);
        }

        if (array_key_exists("purchase_date", $request->getParsedBody())){
            $p_d = $request->getParsedBody()['purchase_date'];
            $sql = "UPDATE shoppinglists SET `purchase_date` = '$p_d' WHERE `id` = '$sl_id'";
            $stmt = $db->query($sql);
        }
    }
    $db = null;
    $response->getBody()->write('{"msg": "Modifié"}');
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});


$app->delete('/api/shoppinglists/{shoppinglistid}', function(Request $request, Response $response){
    $token = get_token_infos($request);
    $sl_id = $request->getAttribute('shoppinglistid');

    try
   {
       $dbconn = new DB\DBConnection();
       $db = $dbconn->connect();

       $sql = "SELECT * FROM shoppinglists WHERE username = '" . $token->username . "'";
       $stmt = $db->query($sql);
       $shoppinglists = $stmt->fetchAll(PDO::FETCH_OBJ);
       $db = null;
       
   } catch( PDOException $e ) {
       // response : 500 : PDO Error (DB)
       $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
       return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    $db = $dbconn->connect();
    if ($shoppinglists) {
        $sql = "DELETE FROM shoppinglists_articles WHERE shoppinglist_id = '$sl_id'";
        $db->query($sql);

        $sql = "DELETE FROM shoppinglists WHERE id = '$sl_id'";
        $db->query($sql);
    }
    $response->getBody()->write('{"msg": "Supprimé"}');
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});


$app->get('/api/shoppinglists/{shoppinglistid}/articles', function(Request $request, Response $response){
    $token = get_token_infos($request);
    $sl_id = $request->getAttribute('shoppinglistid');
    $articles_list = array();

    try
   {
       $dbconn = new DB\DBConnection();
       $db = $dbconn->connect();

       $sql = "SELECT * FROM shoppinglists WHERE username = '" . $token->username . "'";
       $stmt = $db->query($sql);
       $shoppinglists = $stmt->fetch(PDO::FETCH_OBJ);
       $db = null;
       
   } catch( PDOException $e ) {
       // response : 500 : PDO Error (DB)
       $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
       return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    if($shoppinglists) {
        $articles = get_articles($shoppinglists->id);

        foreach($articles as $article){
            $articles_list[] = $article;
        }

        $db = null;
        $response->getBody()->write(json_encode($articles_list));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } else {
        $response->getBody()->write('{"error": {"msg": "La liste est introuvable"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

});


$app->post('/api/shoppinglists/{shoppinglistid}/articles', function(Request $request, Response $response){
    $token = get_token_infos($request);
    $sl_id = $request->getAttribute('shoppinglistid');
    $ids_array = array();

    try
   {
       $dbconn = new DB\DBConnection();
       $db = $dbconn->connect();

       $sql = "SELECT * FROM shoppinglists WHERE username = '" . $token->username . "'";
       $stmt = $db->query($sql);
       $shoppinglists = $stmt->fetch(PDO::FETCH_OBJ);

   } catch( PDOException $e ) {
       // response : 500 : PDO Error (DB)
       $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
       return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    if($shoppinglists) {
        $ids_array = $request->getParsedBody();

        foreach($ids_array as $articleid){
            try 
            {
                $sql = "SELECT article_id FROM shoppinglists_articles WHERE article_id = '$articleid' AND shoppinglist_id = '$sl_id'";
                $stmt = $db->query($sql);
                $article_id = $stmt->fetchAll(PDO::FETCH_OBJ);

            } catch( PDOException $e ) {
                // response : 500 : PDO Error (DB)
                $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            if($article_id) {
                $sql = "UPDATE `shoppinglists_articles` SET `quantity` = ((SELECT `quantity` FROM `shoppinglists_articles` WHERE `shoppinglist_id` = '$sl_id' AND `article_id` = '$articleid') + 1) WHERE `shoppinglist_id` = '$sl_id' AND `article_id` = '$articleid'";
                $db->query($sql);
            } else {
                $sql = "INSERT INTO `shoppinglists_articles` (`shoppinglist_id`, `article_id`, `quantity`) VALUES ('$sl_id', '$articleid', 1)";
                $db->query($sql);
            }
            $db=null;
            $response->getBody()->write('{"msg": "Ajouté"}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        }
    }
});

$app->put('/api/shoppinglists/{shoppinglistid}/articles', function(Request $request, Response $response){
    $token = get_token_infos($request);
    $sl_id = $request->getAttribute('shoppinglistid');
    $ids_array = array();

    try
   {
       $dbconn = new DB\DBConnection();
       $db = $dbconn->connect();

       $sql = "SELECT * FROM shoppinglists WHERE username = '" . $token->username . "'";
       $stmt = $db->query($sql);
       $shoppinglists = $stmt->fetch(PDO::FETCH_OBJ);

   } catch( PDOException $e ) {
       // response : 500 : PDO Error (DB)
       $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
       return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    if($shoppinglists) {
        $ids_array = $request->getParsedBody();

        foreach($ids_array as $articleid){
            try 
            {
                $sql = "SELECT article_id FROM shoppinglists_articles WHERE article_id = '$articleid' AND shoppinglist_id = '$sl_id'";
                $stmt = $db->query($sql);
                $article_id = $stmt->fetchAll(PDO::FETCH_OBJ);

            } catch( PDOException $e ) {
                // response : 500 : PDO Error (DB)
                $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            if($article_id) {
                $sql = "UPDATE `shoppinglists_articles` SET `quantity` = ((SELECT `quantity` FROM `shoppinglists_articles` WHERE `shoppinglist_id` = '$sl_id' AND `article_id` = '$articleid') + 1) WHERE `shoppinglist_id` = '$sl_id' AND `article_id` = '$articleid'";
                $db->query($sql);
            } else {
                $sql = "INSERT INTO `shoppinglists_articles` (`shoppinglist_id`, `article_id`, `quantity`) VALUES ('$sl_id', '$articleid', 1)";
                $db->query($sql);
            }
            $db = null;
            $response->getBody()->write('{"msg": "Ajouté"}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        }
    }
});

$app->delete('/api/shoppinglists/{shoppinglistid}/articles', function(Request $request, Response $response){
    $token = get_token_infos($request);
    $sl_id = $request->getAttribute('shoppinglistid');
    $ids_array = array();

    try
   {
       $dbconn = new DB\DBConnection();
       $db = $dbconn->connect();

       $sql = "SELECT * FROM shoppinglists WHERE username = '" . $token->username . "'";
       $stmt = $db->query($sql);
       $shoppinglists = $stmt->fetch(PDO::FETCH_OBJ);

   } catch( PDOException $e ) {
       // response : 500 : PDO Error (DB)
       $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
       return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    if($shoppinglists){
        $sql="DELETE FROM shoppinglists_articles WHERE `shoppinglist_id` = $sl_id";
        $stmt = $db->query($sql);
    }
    $db = null;
    $response->getBody()->write('{"msg": "Tous les éléments de la liste ont été supprimés"}');
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});


$app->get('/api/shoppinglists/{shoppinglistid}/articles/{articleid}', function(Request $request, Response $response) {
    $parsedToken = get_token_infos($request);
    $username = $parsedToken->username;
    $idsl = $request->getAttribute("shoppinglistid");
    $idart = $request->getAttribute("articleid");

    $sql = "SELECT `id` FROM `shoppinglists` WHERE `username` = '$username' AND `id`= '$idsl'";
    try
    {
        $dbconn = new DB\DBConnection();
        $db = $dbconn->connect();

        $stmt = $db->query($sql);
        $test = $stmt->fetch(PDO::FETCH_OBJ);

        if ($test) {
            $sql = "SELECT `article_id` FROM `shoppinglists_articles` WHERE `shoppinglist_id` = '$idsl' AND `article_id`= '$idart'";
            $stmt = $db->query($sql);
            $article = $stmt->fetch(PDO::FETCH_OBJ);

            if ($article) {
                $api = new OpenFoodFacts\Api('food', 'fr');
                $fulldesc = $api->getProduct($idart);
                $fulldescarr = $fulldesc->getData();
                $shortdesc = get_product_short_data_array($fulldescarr);
    
                $db = null;
                $response->getBody()->write(json_encode($shortdesc));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } else {
                $db = null;
                $response->getBody()->write('{"error": {"msg": "Liste introuvable."}}');
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
        }
        $db = null;
    } catch (PDOException $e) {
        $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->get('/api/shoppinglists/{shoppinglistid}/articles/{articleid}/full', function(Request $request, Response $response) {
    $parsedToken = get_token_infos($request);
    $username = $parsedToken->username;
    $idsl = $request->getAttribute("shoppinglistid");
    $idart = $request->getAttribute("articleid");

    $sql = "SELECT `id` FROM `shoppinglists` WHERE `username` = '$username' AND `id`= '$idsl'";
    try
    {
        $dbconn = new DB\DBConnection();
        $db = $dbconn->connect();

        $stmt = $db->query($sql);
        $test = $stmt->fetch(PDO::FETCH_OBJ);

        if ($test) {
            $sql = "SELECT `article_id` FROM `shoppinglists_articles` WHERE `shoppinglist_id` = '$idsl' AND `article_id`= '$idart'";
            $stmt = $db->query($sql);
            $article = $stmt->fetch(PDO::FETCH_OBJ);

            if ($article) {
                $api = new OpenFoodFacts\Api('food', 'fr');
                $fulldesc = $api->getProduct($idart);
                $fulldescarr = $fulldesc->getData();

                $db = null;
                $response->getBody()->write(json_encode($fulldescarr));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } else {
                $db = null;
                $response->getBody()->write('{"error": {"msg": "Liste introuvable."}}');
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
        }
        $db = null;
    } catch (PDOException $e) {
        $response->getBody()->write('{"error": {"msg": "' . $e->getMessage() . '"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});
