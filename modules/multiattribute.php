<?php

Class BulkAttributes {

    function __construct() {
        $this->attributeRequests = [];
    }
    static function factory() {
        return new self();
    }

    function setup($access_token, $email, $attributes) {
        $this->attributeRequests[] = array(
            "access_token" => $access_token,
            "email" => $email,
            "attributes" => $attributes
          );
    }

    function retry($curl) {
        echo "retrying request".$_ENV['br'];
        sleep(1);
        $resp = curl_exec($curl);
        $err = curl_error($curl);
        return array(
            "error" => $err,
            "response" => json_decode($resp),
            "code" => curl_getinfo($curl, CURLINFO_HTTP_CODE)
        );
    }

    function process() {
        $success = 0;
        $fail = 0;

        // array of curl handles
        $multiCurl = array();
        // data to be returned
        $result = array();
        $mh = curl_multi_init();
        $i = 0;
        $emails = [];
        while( $attr = array_shift( $this->attributeRequests ) ) {  
            $emails[] = $attr['email'];

            $multiCurl[$i] = TBP::factory($attr['access_token'])->constructRequest(
            "users/".urlencode($attr['email']),
            "PATCH",
            array(
                "data" => array(
                "type" => 'user',
                "attributes" => array(
                    "state" => $attr['attributes']
                )
                )
            )
            );
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
                    case 503:
                    case 403:
                        $resp = $this->retry($multiCurl [$k]);
                        $response = $resp['response'];
                        $err = $resp['error'];
                        $code = $resp['code'];
                        if ($code === 204) {
                            $success++;
                            auditLog("Updated ".$emails[$k], true);
                            break;
                        } else if ($response && $response->errors[0]->detail) {
                            $fail++;
                            auditLog($response->errors[0]->detail, true);
                        } else {
                            $fail++;

                            return array(
                                "success" => $success,
                                "fail" => $fail,
                                "error" => "Exceeded API Limit"
                            );
                            break;
                        }
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
}

function multiAttribute($access_token, $filename) {
    $headers = false;
    $people = [];

    $process = BulkAttributes::factory();

    $done = 0;
    $fail = 0;
    $processed = 0;

    //Counts the number of users to be updated
    $number_of_users = getLineCount($filename);


    if (($open = fopen($filename, "r")) !== FALSE) 
    {

    $attribute_ids = [];
    while (($data = fgetcsv($open, 1000, ",")) !== FALSE) 
    {        
        if (!$headers) {
        $headers = $data;

        if (count($headers) <= 1) {
            auditLog("Data doesn't appear to match expected csv file", true);
            exit;
        }

        //intialise the array to hold the attributes to be updated
        $attributes = [];
        //adds the attributes to be updated to the array
        for($pos=$_ENV['ATTRIBUTE_OFFSET']; $pos< count($headers); $pos++)
        {
            $attributes[] = $headers[$pos];
        }
        $attribute_ids = getOrCreateAttributes($attributes, $access_token);

        auditLog(trim($number_of_users) . " users to be updated", true);
        auditLog((count($headers) - $_ENV['ATTRIBUTE_OFFSET'])." attributes to be updated", true);

        $time_estimate = ceil((intval($number_of_users) * 0.021)/60);
        auditLog("Estimated duration: ".$time_estimate." mins", true);
        if ($time_estimate >= 10) {
            auditLog("Go make a coffee and come back later", true);
        }

        } else {
        $processed++;
        $email = $data[0];
        if (strpos($email, "@") <= 0) {
            auditLog("Data doesn't appear to match expected csv file. Was expecting email but got: ".$email, true);
            exit;
        }

        // get each attribute
        $attributes = array();
        for($y=$_ENV['ATTRIBUTE_OFFSET']; $y < count($data); $y++)
        {
            $attributes[] = TBP::generateAttributeObj(
                $attribute_ids[$y-$_ENV['ATTRIBUTE_OFFSET']],
                $data[$y]
            );
        }

        //update the attributes
        $process->setup($access_token, $email, $attributes);
        
        if ($processed % $_ENV['PROCESS_AT_A_TIME'] === 0) {
            $response = $process->process();
            $done += $response['success'];
            $fail += $response['fail'];
            if (isset($response['error'])) {
                auditLog($response['error'], true);
                auditLog("Success: ".$done.", fail: ".$fail.", total: ".$number_of_users, true);
                exit;
            }
        }

        }
    }

    fclose($open);
    }

    $response = $process->process();
    $done += $response['success'];
    $fail += $response['fail'];
    if (isset($response['error'])) {
        auditLog($resonse['error'], true);
        auditLog("Success: ".$done.", fail: ".$fail.", completed: ".($done+$fail)."./".$number_of_users, true);
        exit;
    }
    return array(
        "success" => $done,
        "fail" => $fail,
        "total" => $number_of_users
    );
}