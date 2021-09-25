<?php

if (!isset($_ENV['TBP_API_REDIRECT_URL'])) {
  echo "Expecting $"."_ENV['TBP_API_REDIRECT_URL']";
  exit;
}


class TBP {

  public static function getLoginURL($client_id, $client_secret) {
    return self::getProvider($client_id, $client_secret, $_ENV['TBP_API_REDIRECT_URL'])->getAuthorizationUrl();

  }

  public static function getProvider($client_id, $client_secret, $redirect_url) {
    if (!$client_id || !$client_secret) {
      throw new Exception("Expecting client_id and client_secret");
    }
    return new \League\OAuth2\Client\Provider\GenericProvider([
      'clientId'                => $client_id,    // The client ID assigned to you by the provider
      'clientSecret'            => $client_secret,    // The client password assigned to you by the provider
      'redirectUri'             => $_ENV['TBP_API_REDIRECT_URL'],
      'urlAuthorize'            => 'https://api.thebotplatform.com/oauth2/auth',
      'urlAccessToken'          => 'https://api.thebotplatform.com/oauth2/token',
      'urlResourceOwnerDetails' => 'https://service.example.com/resource'
    ]);
  }

}