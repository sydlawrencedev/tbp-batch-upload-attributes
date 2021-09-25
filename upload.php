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


if (!isset($_FILES["fileToUpload"])) {
  header("Location: form.php");
  exit();
}

auditLog("New Form Input-{$audit_log_date}");
// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
  auditLog("Error Uploading form", true);
// if everything is ok, try to upload file
} else {
  if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], "uploads/{$filename}.csv")) {
    auditLog("The file ". htmlspecialchars( basename( $_FILES["fileToUpload"]["name"])). " has been uploaded.", true);
  } else {
    auditLog("Error Uploading form", true);
  }
}

//Store the CSV in an array
$fullFilename = "uploads/{$filename}.csv";

$response = multiAttribute($access_token, $fullFilename);

echo $response['success']." success / ".$response['fail']." failed / ".trim($response['total'])." total";

echo "<br/>";
if ($_ENV['DISPLAY_LOG'] === "TRUE") {
  echo "<br/>";

  $end_time = time();
  $time_to_complete = $end_time - $start_time;
  $time_per_person = ($time_to_complete/$response['total']);
  echo $time_to_complete. " seconds to complete - ".$time_per_person."s per person = ".(($time_per_person*40000)/60/60). " hours for 40000";

  echo "<br/>";
}
?>
<a href="form.php">Go again</a>