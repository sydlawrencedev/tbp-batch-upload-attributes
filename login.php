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

        <nav class="navbar navbar-light bg-light">

        <a class="navbar-brand" href="#">
            Bot Tools
        </a>
        <ul class="navbar-nav me-auto mb-2 mb-lg-0 ml-auto">
            <li class="nav-item">
                <a class="nav-lin" href="https://dev.thebotplatform.com/" target="_blank" rel="noopener noreferrer">Built on top of The Bot Platform API</a>
                &nbsp;
                <a class="nav-lin" href="https://github.com/sydlawrencedev/tbp-batch-upload-attributes" target="_blank" rel="noopener noreferrer">Code on Github</a>
            </li>

        </ul>


        </nav>

        <h1>Login</h1>
            <p>To use this system you need your client id and client secret from your API access on <a href="https://thebotplatform.com" target="_blank">The Bot Platform</a></p>
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
                <p>&nbsp;</p>
            <p><a href="https://github.com/sydlawrencedev/tbp-batch-upload-attributes/blob/main/LICENSE" target="_blank">Apache License 2.0</a> Open Source<p>
        </div>
    </div>
</body>

</html>