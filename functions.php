<?php

require 'vendor/autoload.php';

ini_set('session.gc_maxlifetime', 3600);

// each client should remember their session id for EXACTLY 1 hour
session_set_cookie_params(3600);

session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Load our environment variables from the ._ENV file:
(Dotenv\Dotenv::createImmutable(__DIR__, "project.env"))->load();



$databaseDirectory = __DIR__ . "/myDatabase";


function saveCreds($client_id, $client_secret)
{
  global $databaseDirectory;
  $credsStore = new \SleekDB\Store("creds", $databaseDirectory, array("timeout" => false));

  $creds = [
    "client_id" => $client_id,
    "client_secret" => $client_secret
   ];
   $results = $credsStore->insert($creds);
}

function checkCreds($client_id) {
  global $databaseDirectory;
  $credsStore = new \SleekDB\Store("creds", $databaseDirectory, array("timeout" => false));
  $creds = $credsStore->findBy(["client_id", "=", $client_id]);
  return $creds;
}



$auditfile = 'auditlog.txt';

function loggedInCheck()
{



  $ip = '127.0.0.1';
  $port = '8080';
  
  $redirect_uri = 'http://'.$ip.':'.$port.'/authorization-code/callback';
  $socket_str = 'tcp://'.$ip.':'.$port;
  
  function http($url, $params=false) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if($params)
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    return json_decode(curl_exec($ch));
  }
  if (isset($_SESSION['tbp_access_token']) && $_SESSION['tbp_access_token']) {

  } else if (php_sapi_name() == 'cli') {
    
    $client_id = "fda383bc-ce8d-4b2b-8f7d-77f3dd7bbeaa";
  $client_secret = "30u~7ZBa~oAxmjcXEL.nMReqW7";
    
    if (php_sapi_name() == 'cli') {
      $_ENV['TBP_API_REDIRECT_URL'] = $redirect_uri;
    }

    $authorize_url = getTBPLoginURL($client_id, $client_secret);
    echo "Open the following URL in a browser to continue\n";
    echo $authorize_url."\n";
    shell_exec("open '".$authorize_url."'");

    function startHttpServer($socketStr) {
      // Adapted from http://cweiske.de/shpub.htm
    
      $responseOk = "HTTP/1.0 200 OK\r\n"
        . "Content-Type: text/plain\r\n"
        . "\r\n"
        . "Ok. You may close this tab and return to the shell.\r\n";
      $responseErr = "HTTP/1.0 400 Bad Request\r\n"
        . "Content-Type: text/plain\r\n"
        . "\r\n"
        . "Bad Request\r\n";
    
      ini_set('default_socket_timeout', 60 * 5);
    
      $server = stream_socket_server($socketStr, $errno, $errstr);
    
      if(!$server) {
        Log::err('Error starting HTTP server');
        return false;
      }
    
      do {
        $sock = stream_socket_accept($server);
        if(!$sock) {
          Log::err('Error accepting socket connection');
          exit(1);
        }
        $headers = [];
        $body    = null;
        $content_length = 0;
        //read request headers
        while(false !== ($line = trim(fgets($sock)))) {
          if('' === $line) {
            break;
          }
          $regex = '#^Content-Length:\s*([[:digit:]]+)\s*$#i';
          if(preg_match($regex, $line, $matches)) {
            $content_length = (int)$matches[1];
          }
          $headers[] = $line;
        }
        // read content/body
        if($content_length > 0) {
          $body = fread($sock, $content_length);
        }
        // send response
        list($method, $url, $httpver) = explode(' ', $headers[0]);
        if($method == 'GET') {
          #echo "Redirected to $url\n";
          $parts = parse_url($url);
          #print_r($parts);
          if(isset($parts['path']) && $parts['path'] == '/authorization-code/callback'
            && isset($parts['query'])
          ) {
            parse_str($parts['query'], $query);
            if(isset($query['code']) && isset($query['state'])) {
              fwrite($sock, $responseOk);
              fclose($sock);
              return $query;
            }
          }
        }
        fwrite($sock, $responseErr);
        fclose($sock);
      } while (true);
    }


    $auth = startHttpServer($socket_str);

  
    $code = $auth['code'];

        echo "Getting an access token...\n";
    $response = http($metadata->token_endpoint, [
      'grant_type' => 'authorization_code',
      'code' => $code,
      'redirect_uri' => $redirect_uri,
      'client_id' => $client_id,
      'client_secret' => $client_secret,
    ]);

    if(!isset($response->access_token)) {
      echo "Error fetching access token\n";
      exit(2);
    }

    $access_token = $response->access_token;
    $_SESSION['tbp_access_token'] = $access_token;

  } else {
    // Redirect the user to the authorization URL.
    header('Location: login.php');
    exit;
  }
}

function getTBPLoginURL($client_id, $client_secret) {


  $provider = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId'                => $client_id,    // The client ID assigned to you by the provider
    'clientSecret'            => $client_secret,    // The client password assigned to you by the provider
    'redirectUri'             => $_ENV['TBP_API_REDIRECT_URL'],
    'urlAuthorize'            => 'https://api.thebotplatform.com/oauth2/auth',
    'urlAccessToken'          => 'https://api.thebotplatform.com/oauth2/token',
    'urlResourceOwnerDetails' => 'https://service.example.com/resource'
  ]);
  return $provider->getAuthorizationUrl();
}

function createAttribute($attribute, $bearertoken)
{
  global $auditfile;
        $curl_attributes = curl_init();

    curl_setopt_array($curl_attributes, [
    CURLOPT_URL => "https://api.thebotplatform.com/v1.0/userattributes",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "{\"data\":{\"type\":\"userattribute\",\"attributes\":{\"name\":\"".$attribute."\",\"is_pii\":false}}}",
    CURLOPT_HTTPHEADER => [
        "Accept: application/json",
        "Authorization: Bearer {$bearertoken}"
    ],
    ]);

    $attributes = curl_exec($curl_attributes);
    $err2 = curl_error($curl_attributes);

    curl_close($curl_attributes);

    if ($err2) {
    echo "cURL Error #:" . $err2;
    file_put_contents($auditfile, "cURL Error - {$err2}", FILE_APPEND | LOCK_EX);
    return false;
    } else {
        file_put_contents($auditfile, "Added Attributes - {$attribute}", FILE_APPEND | LOCK_EX);
    //echo $attributes;
    }


    return true;
}

function getAttributes($bearertoken)
{
  global $auditfile;
        $curl_attributes = curl_init();

    curl_setopt_array($curl_attributes, [
    CURLOPT_URL => "https://api.thebotplatform.com/v1.0/userattributes",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "Accept: application/json",
        "Authorization: Bearer {$bearertoken}"
    ],
    ]);

    $attributes = curl_exec($curl_attributes);
    $err2 = curl_error($curl_attributes);

    curl_close($curl_attributes);

    if ($err2) {
      echo "cURL Error #:" . $err2;
      file_put_contents($auditfile, "cURL Error - {$err2}", FILE_APPEND | LOCK_EX);
    } else {
      file_put_contents($auditfile, "Attributes - {$attributes}", FILE_APPEND | LOCK_EX);
    }
    if ($attributes === "Unauthorized") {
      echo "Unauthorized";
      exit;
    }
    $obj = json_decode($attributes);
    return json_decode($attributes);
}

function updateAttributes($bearertoken, $email, $attributes)
{
  global $auditfile;
    $curl = curl_init();
    $postfields = array(
      "data" => array(
        "type" => 'user',
        "attributes" => array(
          "state" => $attributes
        )
      )
    );

  curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.thebotplatform.com/v1.0/users/".urlencode($email),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "PATCH",
    CURLOPT_POSTFIELDS => json_encode($postfields),
    CURLOPT_HTTPHEADER => [
      "Content-Type: application/json",
      "Authorization: Bearer {$bearertoken}"
    ],
  ]);
  
  $response = curl_exec($curl);
  $err = curl_error($curl);
  
  curl_close($curl);
  
  if ($err) {
    echo "cURL Error #:" . $err;
    file_put_contents($auditfile, "cURL Error - {$err}", FILE_APPEND | LOCK_EX);
    return false;
  } else {
    if (!$response) {
      return true;
    }
    try {

      echo json_decode($response)->errors[0]->detail;
    } catch (Exception $e) {

    }
      file_put_contents($auditfile, "Response - {$response}", FILE_APPEND | LOCK_EX);
    return false;
  }  

  if (!$response) {
    return true;
  }



}



?>