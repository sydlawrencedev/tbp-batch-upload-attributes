<?php
require __DIR__ . '/functions.php';

foreach ($argv as $arg) {
  $e=explode("=",$arg);
  if(count($e)==2)
      $_GET[$e[0]]=$e[1];
  else   
      $_GET[$e[0]]=0;
}

$_ENV['TBP_CLI_CLIENT_ID'] = $_GET['client_id'];


loggedInCheck();
$filename = $_GET['filename'];
//Store the CSV in an array
echo "Loading attributes from ".$filename."\n";
$response = multiAttribute($_SESSION['tbp_access_token'], $filename);

echo $response['success']." success / ".$response['fail']." failed / ".trim($response['total'])." total\n";
