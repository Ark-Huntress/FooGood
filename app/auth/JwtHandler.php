<?php namespace Auth;


use \Firebase\JWT\JWT;



/**
 * Classe qui implémente le codeur/décodeur de jetons à l'aide de Firebase JWT
 */
class JwtHandler {
    const TOKEN_PASSPHRASE = "iutsd";  // Const utilisé pour encoder le token 

    protected $jwt_secrect;
    protected $token;
    protected $issuedAt;
    protected $expire;
    protected $jwt;

    /**
     * Class constructeur
     */
    public function __construct()
    {
        // définir la date d'émission du token (date de création)
        $this->issuedAt = time();
        
        // Validité du token (3600 secondes = 1h)
        $this->expire = $this->issuedAt + 3600;

        // Définissez votre secret ou votre signature
        $this->jwt_secrect = self::TOKEN_PASSPHRASE;
    }

    /**
     * Method to encode the token using : 
     *   - $iss : issuer string
     *   - $data : data array that you want to encode in the token
     */ 
    public function _jwt_encode_data($iss,$data){

        $this->token = array(
            // ajouter l'identifiant au token (qui émet le token)
            "iss" => $iss,
            "aud" => $iss,
            // Ajout de l'horodatage actuel au token, pour identifier le moment où le token a été émis.
            "iat" => $this->issuedAt,
            // Token expiration
            "exp" => $this->expire,
            // Payload
            "data"=> $data
        );

        $this->jwt = JWT::encode($this->token, $this->jwt_secrect);
        return $this->jwt;

    }
    
    /**
     * Method to decode the token using : 
     *   - $jwt_token : the token :)

     * Throw Exceptions :
     *   - ExpiredException
     *   - SignatureInvalidException
     *   - BeforeValidException
     *   - DomainException
     *   - UnexpectedValueException

     */ 
    public function _jwt_decode_data($jwt_token){
        try{
            $decode = JWT::decode($jwt_token, $this->jwt_secrect, array('HS256'));
            
            return $decode->data;
        }
        catch(\Firebase\JWT\ExpiredException $e){
            throw $e;
        }
        catch(\Firebase\JWT\SignatureInvalidException $e){
            throw $e;
        }
        catch(\Firebase\JWT\BeforeValidException $e){
            throw $e;
        }
        catch(\DomainException $e){
            throw $e;
        }
        catch(\InvalidArgumentException $e){
            throw $e;
        }
        catch(\UnexpectedValueException $e){
            throw $e;
        }

    }
}