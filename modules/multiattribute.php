<?php

function multiAttribute($access_token, $filename) {
    $headers = false;
    $people = [];

    $done = 0;
    $fail = 0;
    $processed = 0;

    //Counts the number of users to be updated
    $number_of_users = getLineCount($filename);
    auditLog(trim($number_of_users) . " users to be updated");


    if (($open = fopen($filename, "r")) !== FALSE) 
    {

    $attribute_ids = [];
    while (($data = fgetcsv($open, 1000, ",")) !== FALSE) 
    {        
        if (!$headers) {
        $headers = $data;
        //intialise the array to hold the attributes to be updated
        $attributes = [];
        //adds the attributes to be updated to the array
        for($pos=$_ENV['ATTRIBUTE_OFFSET']; $pos< count($headers); $pos++)
        {
            $attributes[] = $headers[$pos];
        }
        $attribute_ids = getOrCreateAttributes($attributes, $access_token);

        auditLog((count($headers) - $_ENV['ATTRIBUTE_OFFSET'])." attributes to be updated");

        } else {
        $processed++;
        $email = $data[0];

        // get each attribute
        $attributes = array();
        for($y=$_ENV['ATTRIBUTE_OFFSET']; $y < count($data); $y++)
        {
            $attributes[] = generateAttributeObj(
            $attribute_ids[$y-$_ENV['ATTRIBUTE_OFFSET']],
            $data[$y]
            );
        }

        //update the attributes
        setupMultipleAttributes($access_token, $email, $attributes);
        
        if ($processed % $_ENV['PROCESS_AT_A_TIME'] === 0) {
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

    return array(
        "success" => $done,
        "fail" => $fail,
        "total" => $number_of_users
    );
}