<?php

require_once __DIR__ . '/../auth/JwtHandler.php';
require_once __DIR__ . '/../auth/Exceptions.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

function get_product_short_data_array($product_full_data){
    $shortproduct = null;

    //check if product description contains mandatory fields
    if (array_key_exists("id", $product_full_data) && array_key_exists("product_name_fr", $product_full_data) &&
    !empty($product_full_data["product_name_fr"]) && array_key_exists("image_url", $product_full_data) &&
    !empty($product_full_data["image_url"]) && array_key_exists("nutriscore_grade", $product_full_data) &&
    !empty($product_full_data["nutriscore_grade"]))
    {
        // create an array with following fields :
        // - id : product's id (barcode)
        // - title : concatenization of <Brand (if exists)> - <Product Name Fr> - <Quantity (if exists)> // - image_url : thumb image of product
        // - nutriscore_letter : nutriscore grade letter (A B C D E)
        // - nutriscore : nutriscore number from 1 (very bad) to 5 (excellent) - 0 = undefined
        $shortproduct = array();
        $shortproduct["id"] = $product_full_data["id"];
        $shortproduct["title"] = 
        (array_key_exists("brand", $product_full_data)?$product_full_data["brands"]." -
        ":"").$product_full_data["product_name_fr"].(array_key_exists("quantity", $product_full_data)?"-
        ".$product_full_data["quantity"]:"");
            $shortproduct["image_url"] = $product_full_data["image_url"];
            $shortproduct["nutriscore_letter"] = $product_full_data["nutriscore_grade"];

            $shortproduct["nutriscore"] = 0;

            if(!is_null($shortproduct["nutriscore_letter"]))
            {
                switch($shortproduct["nutriscore_letter"])
                {
                    case 'e':
                        $shortproduct["nutriscore"] = 1;
                        break;

                    case 'd':
                        $shortproduct["nutriscore"] = 2;
                        break;
                    
                    case 'c':
                        $shortproduct["nutriscore"] = 3;
                        break;
                    
                    case 'b':
                        $shortproduct["nutriscore"] = 4;
                        break;

                    case 'a':
                        $shortproduct["nutriscore"] = 5;
                        break;
                }
            }
        }
    return $shortproduct;
}

/** 
 * Search article on openfoodfact api
 */
$app->get('/api/openfoodfacts/articles', function(Request $request, Response $response){
    $search_param = $request->getQueryParam("search", $default = null);

    try {
        if(!is_null($search_param) && !empty($search_param)) {
            $api = new OpenFoodFacts\Api('food', 'fr');
            $result = $api->search($search = $search_param, 1, 100);
            $search_res = array();
    
            foreach($result as $item){
                if(!is_null($item)) {
                    $getdata = $item ->getData();
                    $search_res[] = get_product_short_data_array($getdata);
                }
            } 
            $response->getBody()->write(json_encode($search_res));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write('{"error": {"msg": Not Acceptable}}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(406);
        }
    } catch(OpenFoodFacts\Exception\ProductNotFoundException $e) {
        $response->getBody()->write('{"error": {"msg": "Article with barcode ' . $articleid . ' not found !"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }
   
});

/**
 * Get specified article on openfoodfact api via article's id
 */
$app->get('/api/openfoodfacts/articles/{articleid}', function( Request $request, Response $response){
    $articleid = $request->getAttribute('articleid');

    try {
        $api = new OpenFoodFacts\Api('food', 'fr');

        $desc = $api->getProduct($articleid);
        $descdata = $desc->getData();

        $descshort = get_product_short_data_array($descdata);

        $response->getBody()->write(json_encode($descshort));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch(OpenFoodFacts\Exception\ProductNotFoundException $e) {
        $response->getBody()->write('{"error": {"msg": "Article with barcode ' . $articleid . ' not found !"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }
});

/**
 * Get specified article on openfoodfact api via article's id
 */
$app->get('/api/openfoodfacts/articles/{articleid}/full', function (Request $request, Response $response){
    $articleid = $request->getAttribute('articleid');

    try {
        $api = new OpenFoodFacts\Api('food', 'fr');

        $desc = $api->getProduct($articleid);
        $descdata = $desc->getData();

        $response->getBody()->write(json_encode($descdata));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch(OpenFoodFacts\Exception\ProductNotFoundException $e) {
        $response->getBody()->write('{"error": {"msg": "Article with barcode ' . $articleid . ' not found !"}}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }
});