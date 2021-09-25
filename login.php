<?php
require 'vendor/autoload.php';
require __DIR__ . '/functions.php';

$client_id = false;
$client_secret = false;
if (isset($_GET['id'])) {
    $client_id = $_POST['client_id'];
    $_SESSION['client_id'] = $client_id;
}
if (isset($_SESSION['client_id'])) {
    $client_id = $_SESSION['client_id'];
}


if ($client_id) {
    $creds = checkCreds($client_id);
    if ($creds) {
        try {
            $_SESSION['client_secret'] = $creds[0]['client_secret'];
        } catch (Exception $e) {

        }
    }
}

if (isset($_GET['secret'])) {
    $client_secret = $_POST['client_secret'];
    $_SESSION['client_secret'] = $client_secret;
}

if (isset($_SESSION['client_secret'])) {
    $client_secret = $_SESSION['client_secret'];
}


if ($client_secret) {
    header('Location: tbplogin.php');
    exit;
}


?>

<!DOCTYPE html>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-F3w7mX95PdgyTmZZMECAngseQB83DfGTowi0iMjiWaeVhAn4FJkqJByhZMI3AhiU" crossorigin="anonymous">
<html>
<body>
    <div class="container">
        
        <div class="mb-3">

            <h1>        <img src="https://d2wditzb2a0ndx.cloudfront.net/1631196712/img/logos/logo-tbp.png" alt="The Bot Platform Logo" width="50" height="50">
 Temp Tools Login</h1>
            <p>To use this system you need your client id and client secret from your API access on The Bot Platform</p>
            <p>You also need to set your API access to "Authorization Code" and you need to set your Redirect URI to <code><?php echo $_ENV['TBP_API_REDIRECT_URL']; ?></code>
            <?php if (!$client_id) { ?>
                <form method="post" action="login.php?id">
                    <p>
                        <label>Client ID
                        <input type="text" name="client_id"/>
                    </label>
                    </p>
                    <input type="submit" value="Login"/>
                </form>
            <?php } else { ?>

                <form method="post" action="login.php?secret">
                    <h1>You're new here...</h1>                
                    <label>Client Secret
                        <input type="password" name="client_secret"/>
                    </label>
                    <input type="submit" value="Login"/>

                </form>

            <?php } ?>
        </div>
    </div>
</body>

</html>