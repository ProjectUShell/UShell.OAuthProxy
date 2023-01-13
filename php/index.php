<?php

  ##############################################################################################
  # OAUTH-PROXY for the 'UShell' SPA     (https://github.com/ProjectUShell/UShell.OAuthProxy)  #
  #                                                                                            #
  # this proxy solves the problem, that we dont want to include any oauth-client-secrets into  #
  # the single page application, because these secrets must not be deliverd to the browser!    #
  ##############################################################################################

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
       header("HTTP/1.1 200 OK");

        if (isset($_SERVER['HTTP_ORIGIN'])) {
          header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        }
        else{
          header("Access-Control-Allow-Origin: *");
        }
        header('Access-Control-Allow-Credentials: false');
        header('Access-Control-Max-Age: 86400');
      

      if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");         
    
      if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        
      exit();
    }

  $state = $_GET["state"];
  $provideRedirectUrlWhenRequestingToken = false;
  $pos = strpos($state, '@');
  if($pos == false){
    $pos = strpos($state, '%40');
    if($pos == false){
      print "<html><head></head><body>INVALID STATE (must be \"RANDOM@PROFILE\")</body></html>";
      exit();
      #######
    }
    else{
      $oAuthProfile = substr($state, $pos + 3);
    }
  }
  else{
    $oAuthProfile = substr($state, $pos + 1 );
  }

  ##############################################################################################
  # REDIRECTION-PROFILES                                                                       #
  ##############################################################################################
  
  switch ($oAuthProfile) {
  
    #NOTE: for this clientId githup will only accept requests with a callback-url to 'http://localhost:3000'!
    case 'github_lh3000':
      $logonUrl =         'https://github.com/login/oauth/authorize?scope=repo read:user';
      $tokenRetrivalUrl = 'https://github.com/login/oauth/access_token';
      $clientId =         '1c295a48ec933ccdf6b7';
      $clientSecret =     '345fe992a7255b396a0b4ab47dafcd54f127c924';
      break;
      
    #NOTE: for this clientId githup will only accept requests with a callback-url to 'http://localhost:4200'!
    case 'github_lh4200':
      $logonUrl =         'https://github.com/login/oauth/authorize?scope=repo read:user';
      $tokenRetrivalUrl = 'https://github.com/login/oauth/access_token';
      $clientId =         '13a603166a688a194a43';
      $clientSecret =     '461f7850cbebe892ca7b4f465c66620918dfc73e';
      break;
      
    default:
      print "<html><head></head><body>INVALID PROFILE(\"".$oAuthProfile."\")</body></html>";
      exit();
      #######
  }
  
  ##############################################################################################
  
  $backRedirectionUri = $_GET["redirect_uri"];
  $lowerBackRedirectionUri = strtolower($backRedirectionUri);
  
  #TODO: whitelisting!!!!
  #     if (str_starts_with($lowerBackRedirectionUri,"https://kornsw.de"){ }
  #else if (str_starts_with($lowerBackRedirectionUri,"https://localhost:")){ }
  #if (str_contains($backRedirectionUri,"://localhost:")){ 
  #}
  #else{
  #  print "<html><head></head><body>INVALID REDIRECT URI \".$backRedirectionUri.\" - not whitelisted!</body></html>";
  #  exit();
  #  #######
  #}
  
  $oldToken = $_GET["old_token"];
  $loginHint = $_GET["login_hint"];
 
  $code = $_GET['code'];
  if($code == ''){
  
    $firstSeparator = "&";
    if(strpos($logonUrl, '?') == false){
      $firstSeparator = "?";
    }
  

    ################################################################################################################
    # DYNMAIC PLACEHOLDERS FOR THE AUTH-URL:
      
    $fullRedirectionUri = $logonUrl.$firstSeparator."client_id=".$clientId."&login_hint=".$loginHint."&state=".$state."&redirect_uri=".$backRedirectionUri;
    
    ################################################################################################################
    
    header("HTTP/1.1 307");
    header("Location: ".$fullRedirectionUri);
    
    print "<!DOCTYPE html>\n<html><head></head><body><a href=\"".$fullRedirectionUri."\"</body></html>";
    exit();
    #######
  }
  else{

    ################################################################################################################
    # POST-CONTENT ON TOKEN RETRIVAL:
    
    $content = 'client_id='.$clientId.'&client_secret='.$clientSecret.'&code='.$code.'&grant_type=authorization_code';
    
    //spacial case, for profiles that need the redirect_uri again
    if($provideRedirectUrlWhenRequestingToken){
    
      if($backRedirectionUri == false){
        $backRedirectionUri = $_SERVER['HTTP_REFERER'];
        $idx = strpos($backRedirectionUri,'?');
        if($idx){
          $backRedirectionUri = substr($backRedirectionUri,0,$idx);
        }
      }

      $content = $content.'&redirect_uri='.$backRedirectionUri;
    }
    
    ################################################################################################################
     
    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\nAccept:application/json",
            'method'  => 'POST',
            'content' => $content,
            'ignore_errors' => true
        )
    );
    
    header("HTTP/1.1 200 OK");
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        // should do a check here to match $_SERVER['HTTP_ORIGIN'] to a
        // whitelist of safe domains
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }
    else{
       header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']} ");
       header("Access-Control-Allow-Origin: {$_SERVER['HTTP_REFERER']} ");
    }

    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");         
    
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    
        exit();
    }
    
    //execution the HTTP-POST:
    $context  = stream_context_create($options);
    $result = file_get_contents($tokenRetrivalUrl, false, $context);
    
    //pick the response-code from the http-response-header-line
    preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
    $httpstatus = $match[1];
    
    header('Content-Type: application/json');
  
    if ($httpstatus != 200) {
      if ($result === FALSE) {
        $escapedBody = "";
      }
      else{
        //TODO: somehow this results in the issue, that the response cant be parsed in the SPA
        //$escapedBody = json_encode($str $escapedBody);
        //$escapedBody = str_replace('\"', '', $escapedBody);
        //WORKARROUND:
        $escapedBody = base64_encode($result);
      }
      
      //NEVER EXPOSE THE CLIENT-SECRET!!!
      $content = str_replace($clientSecret, '********', $content);
      
      print '{ "error": "Token could not be requested from: \''.$tokenRetrivalUrl.'\' (HTTP-'.$httpstatus.'). POST-CONTENT: \''.$content.'\'. RESPONSE-BODY (base64): \''.$escapedBody.'\'."}';
    }
    else{
      print $result;
    }
    
    //var_dump($result);
    
  }
?>