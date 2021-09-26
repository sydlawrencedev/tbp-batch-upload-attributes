<?php

if (!isset($_ENV['TBP_API_REDIRECT_URL'])) {
  echo "Expecting $"."_ENV['TBP_API_REDIRECT_URL']";
  exit;
}

if (!isset($_ENV['TBP_BASE_URL'])) {
  $_ENV['TBP_BASE_URL'] = "https://api.thebotplatform.com/v1.0/";
}
if (!isset($_ENV['TBP_AUTH_URL'])) {
  $_ENV['TBP_AUTH_URL'] = "https://api.thebotplatform.com/oauth2/auth";
}
if (!isset($_ENV['TBP_TOKEN_URL'])) {
  $_ENV['TBP_TOKEN_URL'] = "https://api.thebotplatform.com/oauth2/token";
}

class TBP {

  static function factory($access_token) {
    $obj = new TBP($access_token);
    return $obj;
  }

  function __construct($access_token) {
    $this->access_token = $access_token;
  }

  public static function getLoginURL($client_id, $client_secret) {
    return self::getProvider($client_id, $client_secret, $_ENV['TBP_API_REDIRECT_URL'])->getAuthorizationUrl();
  }

  public static function getProvider($client_id, $client_secret, $redirect_url = false) {
    if (!$redirect_url) {
      $redirect_url = $_ENV['TBP_API_REDIRECT_URL'];
    }
    if (!$client_id || !$client_secret) {
      throw new Exception("Expecting client_id and client_secret");
    }
    return new \League\OAuth2\Client\Provider\GenericProvider([
      'clientId'                => $client_id,    // The client ID assigned to you by the provider
      'clientSecret'            => $client_secret,    // The client password assigned to you by the provider
      'redirectUri'             => $_ENV['TBP_API_REDIRECT_URL'],
      'urlAuthorize'            => $_ENV['TBP_AUTH_URL'],
      'urlAccessToken'          => $_ENV['TBP_TOKEN_URL'],
      'urlResourceOwnerDetails' => 'https://service.example.com/resource'
    ]);
  }

  function validate() {
    try {
      $attributes = TBP::factory($this->access_token)->getAttributes();
    } catch (Exception $e) {
      return false;
    }
    if ($attributes === false) {
      return false;
    }
    return true;
  }
  
  function constructRequest($method, $requestType = "POST", $postdata = "") {
    if (is_object($postdata) || is_array($postdata)) {
      $postdata = json_encode($postdata);
    }
    $access_token = $this->access_token;
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $_ENV['TBP_BASE_URL'].$method,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $requestType,
      CURLOPT_POSTFIELDS => $postdata,
      
    ]);
    curl_setopt($curl, CURLOPT_HTTPHEADER,[
        "Content-Type: application/json",
        "Authorization: Bearer {$access_token}"
      ]);
    return $curl;
  }

  function request($method, $requestType = "POST", $postdata = "") {
    $curl = $this->constructRequest($method, $requestType, $postdata);
    $resp = curl_exec($curl);
    $err = curl_error($curl);
    return array(
      "error" => $err,
      "response" => $resp
    );
  }

  function getAttributes($access_token = false) {
    if (!$access_token) {
      $access_token = $this->access_token;
    }

    $resp = $this->request("userattributes", "GET", false);
   

    if ($resp['error']) {
      throw new Exception($resp['error']);
      return;
    }
    if ($resp['response'] === "Unauthorized") {
      throw new Exception("Unauthorized");
      return false;
    }
    return json_decode($resp['response']);
  }

  function createAttribute($attribute, $is_pii = false)
  {
    $response = $this->request(
      "userattributes",
      "POST",
      json_encode(array(
        "data" => array(
          "type" => "userattribute",
          "attributes" => array(
            "name" => $attribute,
            "is_pii" => $is_pii
          )
        )
      ))
    );
    if ($response['error']) {
      throw new Exception($response['error']);
      return false;
    }
    return true;
  }

  static function generateAttributeObj($id, $value) {
    return array(
      "userattribute" => array(
        "id" => $id,
      ),
      "value" => $value
    );
  }

  function updateAttributesSingle($email, $attributes) {
    $response = $this->request(
      "users/".urlencode($email),
      "PATCH",
      json_encode(array(
        "data" => array(
          "type" => 'user',
          "attributes" => array(
            "state" => $attributes
          )
        )
      ))
    );
    if ($response['error']) {
      throw new Exception($response['error']);
      return false;
    }
    if (!$response['response']) {
      return true;
    }
    try {
      if (json_decode($response)) {
        echo json_decode($response)->errors[0]->detail;
      }
    }
    catch (Exception $e) {

    }
    return false;
    
  }

}