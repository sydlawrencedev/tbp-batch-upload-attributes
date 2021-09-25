<?php
require __DIR__ . '/functions.php';

loggedInCheck();

$access_token = $_SESSION['tbp_access_token'];

$start_time = time();

date_default_timezone_set("UTC");
$audit_log_date = date("d-m-Y H:i:s");

$target_dir = "uploads/";
$uploadOk = 1;
//grabs the BOT ID from the form and stores it to be used to name the file
$target_new_name = $_SESSION['client_id'];
//Sets new filename for when it is uploaded
$filename = "{$target_new_name}-{$audit_log_date}";

$attribute_offset = 2;


if (!isset($_FILES["fileToUpload"])) {
  header("Location: form.php");
  exit();
}

auditLog("New Form Input-{$audit_log_date}");
// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
  auditLog("Error Uploading form");
// if everything is ok, try to upload file
} else {
  if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], "uploads/{$filename}.csv")) {
    auditLog("The file ". htmlspecialchars( basename( $_FILES["fileToUpload"]["name"])). " has been uploaded.");
  } else {
    auditLog("Error Uploading form");
  }
}

//Store the CSV in an array
$fullFilename = "uploads/{$filename}.csv";
$headers = false;
$people = [];




$done = 0;
$fail = 0;
$processed = 0;

//Counts the number of users to be updated
$number_of_users = getLineCount($fullFilename);
auditLog($number_of_users . " users to be updated");


if (($open = fopen($fullFilename, "r")) !== FALSE) 
{

  $attribute_ids = [];
  while (($data = fgetcsv($open, 1000, ",")) !== FALSE) 
  {        
    if (!$headers) {
      $headers = $data;
      //intialise the array to hold the attributes to be updated
      $attributes = [];
      //adds the attributes to be updated to the array
      for($pos=$attribute_offset; $pos< count($headers); $pos++)
      {
        $attributes[] = $headers[$pos];
      }
      $attribute_ids = getOrCreateAttributes($attributes, $access_token);

      auditLog((count($headers) - $attribute_offset)." attributes to be updated");

    } else {
      $processed++;
      $email = $data[0];

      // get each attribute
      $attributes = array();
      for($y=$attribute_offset; $y < count($data); $y++)
      {
        $attributes[] = generateAttributeObj(
          $attribute_ids[$y-$attribute_offset],
          $data[$y]
        );
      }

      //update the attributes
      setupMultipleAttributes($access_token, $email, $attributes);
      
      if ($processed % 5 === 0) {
        $response = setMultipleAttributes();
        $done += $response['success'];
        $fail += $response['fail'];
      }

    }
  }

  fclose($open);
}

$response = setMultipleAttributes();
  $done += $response['success'];
  $fail += $response['fail'];


echo $done." done / ".$fail." failed / ".$number_of_users." total<br/>";

echo "<br/>";
echo "<br/>";

$end_time = time();
$time_to_complete = $end_time - $start_time;
$time_per_person = ($time_to_complete/$number_of_users);
echo $time_to_complete. " seconds to complete - ".$time_per_person."s per person = ".(($time_per_person*40000)/60/60). " hours for 40000";

echo "<br/>";
?>
<a href="form.php">Go again</a>