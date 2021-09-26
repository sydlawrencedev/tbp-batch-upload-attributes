<?php


session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Load our environment variables from the ._ENV file:
require 'vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__, "project.env"))->load();

if (php_sapi_name() != 'cli') {
  $protocol = "https://";
  if (strpos($_SERVER['HTTP_HOST'], "localhost") > -1) {
    $protocol = "http://";
  }
  $_ENV['TBP_API_REDIRECT_URL']=$protocol.$_SERVER['HTTP_HOST'].$_SERVER['DIR']."tbplogin.php";
}
require 'modules/TBPCLI.php';

if (php_sapi_name() == 'cli') {
  $_ENV['TBP_API_REDIRECT_URL'] = TBPCLI::redirect_uri();
}
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


function exceededAPILimit() {
  auditLog("Exceeded API Limit, please go back try again", true);
  exit;
}

?>