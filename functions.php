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

function loggedInCheck()
{
  if (isset($_SESSION['tbp_access_token']) && $_SESSION['tbp_access_token']) {
    if (!TBP::factory($_SESSION['tbp_access_token'])->validate()) {
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
  $tbp = TBP::factory($access_token);
  try {
    $attributes = $tbp->getAttributes();

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
      $tbp->createAttribute($attribute_name);
      return getOrCreateAttributes($attributes_to_fetch, $access_token);
    }
    
    $attribute_ids_to_be_updated[] = $attribute_id;
  }
  return $attribute_ids_to_be_updated;
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