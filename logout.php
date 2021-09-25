<?php
require __DIR__ . '/functions.php';


session_destroy();
header('Location: login.php');
