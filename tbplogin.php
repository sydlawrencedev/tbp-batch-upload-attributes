<?php

require __DIR__ . '/functions.php';

// get the auth creds
$client_id = $_SESSION['client_id'];
$client_secret = $_SESSION['client_secret'];

$TBPAuth = TBP::getProvider($client_id, $client_secret);
// If we don't have an authorization code then get one
if (!isset($_GET['code'])) {

    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $authorizationUrl = $TBPAuth->getAuthorizationUrl();

    // Get the state generated for you and store it to the session.
    $_SESSION['oauth2state'] = $TBPAuth->getState();

    // Redirect the user to the authorization URL.
    header('Location: ' . $authorizationUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {

    if (isset($_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
    }

    exit('Invalid state');

} else {

    try {

        // Try to get an access token using the authorization code grant.
        $accessToken = $TBPAuth->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        $_SESSION['tbp_access_token'] = $accessToken->getToken();

        $creds = checkCreds($_SESSION['client_id']);
        if (!$creds) {
            saveCreds($_SESSION['client_id'], $_SESSION['client_secret']);
        }
        header('Location: form.php');
        exit;
       

    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        // Failed to get the access token or user details.
        exit($e->getMessage());

    }

}