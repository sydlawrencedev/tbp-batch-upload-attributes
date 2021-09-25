<?php

require 'modules/multiattribute.php';

require 'vendor/autoload.php';

session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Load our environment variables from the ._ENV file:
(Dotenv\Dotenv::createImmutable(__DIR__, "project.env"))->load();

// print_r($_ENV);
// exit;

$protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"],0,strpos( $_SERVER["SERVER_PROTOCOL"],'/'))).'://';


$_ENV['TBP_API_REDIRECT_URL']=$protocol.$_SERVER['HTTP_HOST'].$_SERVER['DIR']."tbplogin.php";



$databaseDirectory = __DIR__ . "/myDatabase";

$_ENV['br'] = "<br/>";
if (php_sapi_name() == 'cli') {
  $_ENV['br'] = "\n";
}

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

function checkAccessToken($access_token)
{
  $attributes = getAttributes($_SESSION['tbp_access_token']);
  if ($attributes === false) {
    return false;
  }
  return true;
}

function loggedInCheck()
{
  
  function http($url, $params=false) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if($params)
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    return json_decode(curl_exec($ch));
  }
  if (isset($_SESSION['tbp_access_token']) && $_SESSION['tbp_access_token']) {
    if (!checkAccessToken($_SESSION['tbp_access_token'])) {
      header('Location: login.php');
      exit;
    }
  } else if (php_sapi_name() == 'cli') {
    

    $ip = '127.0.0.1';
    $port = '8080';
    
    $redirect_uri = 'http://'.$ip.':'.$port.'/authorization-code/callback';
    $socket_str = 'tcp://'.$ip.':'.$port;

    $client_id = $_ENV['TBP_CLI_CLIENT_ID'];

    $creds = checkCreds($client_id);
    if ($creds) {
      $client_secret = $_ENV['TBP_CLI_CLIENT_SECRET'] = $creds[0]['client_secret'];
    } else {
      //get 3 commands from user
      $line = readline("You're new here, what's your client_secret? ");
      $_ENV['TBP_CLI_CLIENT_SECRET'] = $line;
      saveCreds($_ENV['TBP_CLI_CLIENT_ID'], $_ENV['TBP_CLI_CLIENT_SECRET']);      
    }
    $client_secret = $_ENV['TBP_CLI_CLIENT_SECRET'];
    
  

    $_ENV['TBP_API_REDIRECT_URL'] = $redirect_uri;

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
    $response = http('https://api.thebotplatform.com/oauth2/token', [
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

function getLineCount($file)
{
  $str = 'perl -pe \'s/\r\n|\n|\r/\n/g\' ' . escapeshellarg($file) . ' | wc -l';
  return exec($str);
}

function getOrCreateAttributes($attributes_to_fetch, $access_token)
{
  try {
    $attributes = getAttributes($access_token);

    //gets the attribute names and their associated IDs and stores in a new array called result 
    $attribute_names = array_column($attributes->data, 'attributes');
    $result = array_combine(array_column($attributes->data, 'id'),array_column($attribute_names, 'name'));
  } catch (Exception $e) {
    echo "ERROR, whoops";
    exit;
  }
  //initialises the array of IDs to be updated
  $attribute_ids_to_be_updated = [];

  //Grabs the ID of each attribute to be updated in the order they are listed within the spreadsheet
  for($x=0; $x< count($attributes_to_fetch); $x++)
  {
    $attribute_name = $attributes_to_fetch[$x];
    $attribute_id = array_search($attribute_name, $result);
    if (!$attribute_id) {
      echo "attribute ".$attribute_name." does not exist, creating attribute".$_ENV['br'];
      createAttribute($attribute_name, $access_token);
      return getOrCreateAttributes($attributes_to_fetch, $access_token);
    }
    
    $attribute_ids_to_be_updated[] = $attribute_id;
  }
  return $attribute_ids_to_be_updated;
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
      return false;
    }
    $obj = json_decode($attributes);
    return json_decode($attributes);
}

function updateAttributes($bearertoken, $email, $attributes)
{
  $curl = curl_init();

  curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.thebotplatform.com/v1.0/users/".urlencode($email),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "PATCH",
    CURLOPT_POSTFIELDS => json_encode(array(
      "data" => array(
        "type" => 'user',
        "attributes" => array(
          "state" => $attributes
        )
      )
    )),
    CURLOPT_HTTPHEADER => [
      "Content-Type: application/json",
      "Authorization: Bearer {$bearertoken}"
    ],
  ]);
  
  $response = curl_exec($curl);
  $err = curl_error($curl);
  
  curl_close($curl);
  
  if ($err) {
    auditLog("cURL Error #:" . $err);
    return false;
  } else {
    if (!$response) {
      return true;
    }
    try {
      if (json_decode($response)) {
        echo json_decode($response)->errors[0]->detail;
      }
    } catch (Exception $e) {

    }
    auditLog("Response - {$response}");
    return false;
  }  

  if (!$response) {
    return true;
  }
}

function auditLog($str, $force_display = false)
{
  if ($_ENV['DISPLAY_LOG'] !== "FALSE" || $force_display) {
    echo $str.$_ENV['br'];
  }
  if ($_ENV['OUTPUT_LOG'] !== "FALSE") {
    file_put_contents($_ENV['OUTPUT_LOG'], $str, FILE_APPEND | LOCK_EX);
  }
}

function generateAttributeObj($id, $value) {
  return array(
    "userattribute" => array(
      "id" => $id,
    ),
    "value" => $value
  );
}

$multiAttributes = [];
function setupMultipleAttributes($access_token, $email, $attributes)
{
  global $multiAttributes;
  $multiAttributes[] = array(
    "access_token" => $access_token,
    "email" => $email,
    "attributes" => $attributes
  );
}

function exceededAPILimit() {
  auditLog("Exceeded API Limit, please go back try again", true);
  exit;
}

function setMultipleAttributes() {
  global $multiAttributes;
  $success = 0;
  $fail = 0;

  // array of curl handles
  $multiCurl = array();
  // data to be returned
  $result = array();
  $mh = curl_multi_init();
  $i = 0;
  $emails = [];
  while( $attr = array_shift( $multiAttributes ) ) {  
    $emails[] = $attr['email'];

    $fetchURL = "https://api.thebotplatform.com/v1.0/users/".urlencode($attr['email']);
    $multiCurl[$i] = curl_init();
    curl_setopt($multiCurl[$i], CURLOPT_URL,$fetchURL);
    curl_setopt($multiCurl[$i], CURLOPT_RETURNTRANSFER,1);
    curl_setopt($multiCurl[$i], CURLOPT_ENCODING,"");
    curl_setopt($multiCurl[$i], CURLOPT_MAXREDIRS,10);
    curl_setopt($multiCurl[$i], CURLOPT_TIMEOUT,30);
    curl_setopt($multiCurl[$i], CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
    curl_setopt($multiCurl[$i], CURLOPT_CUSTOMREQUEST,"PATCH");
    curl_setopt($multiCurl[$i], CURLOPT_POSTFIELDS,json_encode(array(
      "data" => array(
        "type" => 'user',
        "attributes" => array(
          "state" => $attr['attributes']
        )
      )
    )));
    curl_setopt($multiCurl[$i], CURLOPT_HTTPHEADER,[
      "Content-Type: application/json",
      "Authorization: Bearer {$attr['access_token']}"
    ]);
    curl_multi_add_handle($mh, $multiCurl[$i]); 
    $i++;   
  }

  $index=null;

  do {
    curl_multi_exec($mh,$index);
  } while($index > 0);
  // get content and remove handles
  foreach($multiCurl as $k => $ch) {
    $result[$k] = json_decode(curl_multi_getcontent($ch));
    curl_multi_remove_handle($mh, $ch);

    if (!$result[$k]) {
      switch ($code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
        case 204:
          $success++;
          auditLog("Updated ".$emails[$k], true);
          break;
        case 403:
          $fail++;
          exceededAPILimit();
          break;
        default:
          $fail++;
          auditLog("Error ".$code." ".$emails[$k], true);
      }
    } else {
      $fail++;
      if ($result[$k]->errors[0]->detail) {
        auditLog($result[$k]->errors[0]->detail, true);
      }
    }
  }
  // close
  curl_multi_close($mh);

 

  return array(
    "success" => $success,
    "fail" => $fail
  );

}


?>