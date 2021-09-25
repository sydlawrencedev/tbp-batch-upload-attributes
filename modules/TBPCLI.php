<?php

class TBPCLI {
  public static function login() {
    $ip = '127.0.0.1';
    $port = '8080';
    
    $redirect_uri = 'http://'.$ip.':'.$port.'/authorization-code/callback';
    $socket_str = 'tcp://'.$ip.':'.$port;

    $client_id = $_ENV['TBP_CLI_CLIENT_ID'];

    $creds = checkCreds($client_id);
    if ($creds) {
      $client_secret = $_ENV['TBP_CLI_CLIENT_SECRET'] = $creds[0]['client_secret'];
    } else {
      //get 3 commands from user
      $line = readline("You're new here, what's your client_secret? ");
      $_ENV['TBP_CLI_CLIENT_SECRET'] = $line;
      saveCreds($_ENV['TBP_CLI_CLIENT_ID'], $_ENV['TBP_CLI_CLIENT_SECRET']);      
    }
    $client_secret = $_ENV['TBP_CLI_CLIENT_SECRET'];
    
  

    $_ENV['TBP_API_REDIRECT_URL'] = $redirect_uri;

    $authorize_url = TBP::getTBPLoginURL($client_id, $client_secret);
    echo "Open the following URL in a browser to continue\n";
    echo $authorize_url."\n";
    shell_exec("open '".$authorize_url."'");

    function startHttpServer($socketStr) {
      // Adapted from http://cweiske.de/shpub.htm
    
      $responseOk = "HTTP/1.0 200 OK\r\n"
        . "Content-Type: text/plain\r\n"
        . "\r\n"
        . "Ok. You may close this tab and return to the shell.\r\n";
      $responseErr = "HTTP/1.0 400 Bad Request\r\n"
        . "Content-Type: text/plain\r\n"
        . "\r\n"
        . "Bad Request\r\n";
    
      ini_set('default_socket_timeout', 60 * 5);
    
      $server = stream_socket_server($socketStr, $errno, $errstr);
    
      if(!$server) {
        Log::err('Error starting HTTP server');
        return false;
      }
    
      do {
        $sock = stream_socket_accept($server);
        if(!$sock) {
          Log::err('Error accepting socket connection');
          exit(1);
        }
        $headers = [];
        $body    = null;
        $content_length = 0;
        //read request headers
        while(false !== ($line = trim(fgets($sock)))) {
          if('' === $line) {
            break;
          }
          $regex = '#^Content-Length:\s*([[:digit:]]+)\s*$#i';
          if(preg_match($regex, $line, $matches)) {
            $content_length = (int)$matches[1];
          }
          $headers[] = $line;
        }
        // read content/body
        if($content_length > 0) {
          $body = fread($sock, $content_length);
        }
        // send response
        list($method, $url, $httpver) = explode(' ', $headers[0]);
        if($method == 'GET') {
          #echo "Redirected to $url\n";
          $parts = parse_url($url);
          #print_r($parts);
          if(isset($parts['path']) && $parts['path'] == '/authorization-code/callback'
            && isset($parts['query'])
          ) {
            parse_str($parts['query'], $query);
            if(isset($query['code']) && isset($query['state'])) {
              fwrite($sock, $responseOk);
              fclose($sock);
              return $query;
            }
          }
        }
        fwrite($sock, $responseErr);
        fclose($sock);
      } while (true);
    }


    $auth = startHttpServer($socket_str);

    $code = $auth['code'];

    echo "Getting an access token...\n";
    $response = http('https://api.thebotplatform.com/oauth2/token', [
      'grant_type' => 'authorization_code',
      'code' => $code,
      'redirect_uri' => $redirect_uri,
      'client_id' => $client_id,
      'client_secret' => $client_secret,
    ]);

    if(!isset($response->access_token)) {
      echo "Error fetching access token\n";
      exit(2);
    }

    $access_token = $response->access_token;
    $_SESSION['tbp_access_token'] = $access_token;
  }
}