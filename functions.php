<?php


session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Load our environment variables from the ._ENV file:
require 'vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__, "project.env"))->load();
$protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"],0,strpos( $_SERVER["SERVER_PROTOCOL"],'/'))).'://';
$_ENV['TBP_API_REDIRECT_URL']=$protocol.$_SERVER['HTTP_HOST'].$_SERVER['DIR']."tbplogin.php";


require 'modules/TBP.php';
require 'modules/multiattribute.php';







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
    TBPCLI::login();
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