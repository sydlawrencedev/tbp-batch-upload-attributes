<?php

require __DIR__ . '/functions.php';

$upload_max_size = ini_get('upload_max_filesize');

$post_max_size = ini_get('post_max_size');

$max_execution_time = ini_get('max_execution_time');

$ini = array(
    "upload_max_filesize" => "42M",
    "post_max_size" => "48M",
    "max_execution_time" => "1200",
    "memory_limit" => "128M",
    "output_buffering" => "off"
);

foreach ($ini as $key => $value) {
    echo $key.": ".ini_get($key)." - recommended ".$value."<br/>";
}

