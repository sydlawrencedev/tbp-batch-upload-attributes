<?php
require __DIR__ . '/functions.php';

loggedInCheck();


$access_token = $_SESSION['tbp_access_token'];


date_default_timezone_set("UTC");
$audit_log_date = date("d-m-Y H:i:s");

$target_dir = "uploads/";
$uploadOk = 1;
//grabs the BOT ID from the form and stores it to be used to name the file
$target_new_name = $_SESSION['client_id'];
//Sets new filename for when it is uploaded
$filename = "{$target_new_name}-{$audit_log_date}";
//Audit log file name
$auditfile = 'auditlog.txt';


if (!isset($_FILES["fileToUpload"])) {
  header("Location: form.php");
  exit();
}

file_put_contents($auditfile, "New Form Input-{$audit_log_date}", FILE_APPEND | LOCK_EX);
// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
  echo "Sorry, your file was not uploaded.";
  file_put_contents($auditfile, "Error Uploading form", FILE_APPEND | LOCK_EX);
// if everything is ok, try to upload file
} else {
  if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], "uploads/{$filename}.csv")) {
    echo "The file ". htmlspecialchars( basename( $_FILES["fileToUpload"]["name"])). " has been uploaded.<br/>";
    file_put_contents($auditfile, "The file ". htmlspecialchars( basename( $_FILES["fileToUpload"]["name"])). " has been uploaded.", FILE_APPEND | LOCK_EX);
  } else {
    echo "Sorry, there was an error uploading your file.";
    file_put_contents($auditfile, "Error Uploading form", FILE_APPEND | LOCK_EX);
  }
}

//Store the CSV in an array

if (($open = fopen("uploads/{$filename}.csv", "r")) !== FALSE) 
  {
  
    while (($data = fgetcsv($open, 1000, ",")) !== FALSE) 
    {        
      $array[] = $data; 
    }
  
    fclose($open);
  }


  //Counts the number of users to be updated e.g. rows in the CSV minus the header row
  $number_of_users_to_be_updated = count($array)-1;
  file_put_contents($auditfile, "Number of users to be updated - {$number_of_users_to_be_updated}", FILE_APPEND | LOCK_EX);
  //grabs the header row from the spreadsheet
  $headers = $array[0];

  $number_of_headers = count($headers);
  //removes the name and email from the count so you are left with the number of attributes to be updated
  $number_of_attributes_in_CSV = $number_of_headers-2;
  file_put_contents($auditfile, "Number of attributes to be updated - {$number_of_attributes_in_CSV}", FILE_APPEND | LOCK_EX);
  //intialise the array to hold the names of the attributes to be updated
  $attributes_to_be_updated_array = [];
  //adds the attributes to be updated to a new array called attributes_to_be_updated_array
  for($x=1; $x<= $number_of_attributes_in_CSV; $x++)
  {
    $attribute_position = $x+1;
    $attributes_to_be_updated = $headers[$attribute_position];
    array_push($attributes_to_be_updated_array, $attributes_to_be_updated);
    
  }
  

function getAttributesTemp() {
global $access_token;
global $attributes_to_be_updated_array;
global $number_of_attributes_in_CSV;
//gets the Bots attributes
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
  for($x=0; $x< $number_of_attributes_in_CSV; $x++)
  {
    $attribute_name = $attributes_to_be_updated_array[$x];
    $attribute_id = array_search($attribute_name, $result);
    if (!$attribute_id) {
      echo "attribute ".$attribute_name." does not exist, creating attribute<br/>";
      createAttribute($attribute_name, $access_token);
      return getAttributesTemp();
    }
    
    $attribute_ids_to_be_updated[] = $attribute_id;
  }
  return $attribute_ids_to_be_updated;
}


$attribute_ids_to_be_updated = getAttributesTemp();
$done = 0;
$fail = 0;
//for loop sets attributes using the Bot platform API, runs through until every row within the sheet is completed
echo $number_of_users_to_be_updated . " users to be updated<br/>";
$attribute_code = "";
for($x=1; $x<= $number_of_users_to_be_updated; $x++)
{
  //Gets the current row of data out of the CSV sheet and stores it in a variable
  $data_row = $array[$x];
  
  //Changes the format of the email string so it works with the API call
  $email = $bodytag = $data_row[0];
  $final_attribute_code = '';

  $attributes = array();
  
  for($y=0; $y<$number_of_attributes_in_CSV; $y++)
  {

    $attribute_id_no = $attribute_ids_to_be_updated[$y];
    $updated_value = $data_row[$y+2];
    
    if (($number_of_attributes_in_CSV>1) && ($y != ($number_of_attributes_in_CSV-1)))
    {
      //this happens while there are multiple attributes
      $attributes[] = array(
        "userattribute" => array(
          "id" => $attribute_id_no,
        ),
        "value" => $updated_value
      );
    }
    else if (($number_of_attributes_in_CSV>1) && ($y = ($number_of_attributes_in_CSV-1)))
    {
      //last attribute to be returned
      $attributes[] = array(
        "userattribute" => array(
          "id" => $attribute_id_no,
        ),
        "value" => $updated_value
      );
    
    }
    else
    {
      $attributes[] = array(
        "userattribute" => array(
          "id" => $attribute_id_no,
        ),
        "value" => $updated_value
      );
    }

  }
  //updates the attributes and resets the JSON string ready for the next row of data from the spreadsheet
  $updated = updateAttributes($access_token, $email, $attributes);

  if ($updated) {
    echo "Updated ".$email."<br/>";
    $done++;
  } else {
    echo "<br/>";
    $fail++;
    // echo "Not able to update ".$email.", no user found<br/>";

  }


}
echo $done." done / ".$fail." failed / ".$number_of_users_to_be_updated." total<br/>";

echo "<br/>";
echo "<br/>";
echo "<br/>";
?>
<a href="form.php">Go again</a>