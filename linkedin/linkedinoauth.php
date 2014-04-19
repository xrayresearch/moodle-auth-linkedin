<?php

/*
 * Abraham Williams (abraham@abrah.am) http://abrah.am
 *
 * The first PHP Library to support OAuth for linkedin's REST API.
 */

/**
 * linkedin OAuth class
 */
class linkedinOAuth {
  /* Contains the last HTTP status code returned. */
  public $http_code;
  /* Contains the last API call. */
  public $url;
  /* Set timeout default. */
  public $timeout = 30;
  /* Set connect timeout. */
  public $connecttimeout = 30; 
  /* Verify SSL Cert. */
  public $ssl_verifypeer = FALSE;
  /* Respons format. */
  public $format = 'json';
  /* Decode returned json data. */
  public $decode_json = TRUE;
  /* Contains the last HTTP headers returned. */
  public $http_info;
  /* Immediately retry the API call if the response was not successful. */
  public $client_id;
  public $client_secret;
  public $token;




  /**
   * Set API URLS
   */
  function accessTokenURL()  { return 'https://www.linkedin.com/uas/oauth2/accessToken'; }
  function authenticateURL() { return 'https://api.linkedin.com/oauth/authenticate'; }
  function authorizeURL()    { return 'https://api.linkedin.com/oauth/authorize'; }
  function requestTokenURL() { return 'https://api.linkedin.com/oauth/request_token'; }

  /**
   * Debug helpers
   */
  function lastStatusCode() { return $this->http_status; }
  function lastAPICall() { return $this->last_api_call; }

  /**
   * construct linkedinOAuth object
   */
  function __construct($consumer_key, $consumer_secret, $oauth_token = NULL) {
	  $this->client_id=$consumer_key;
	  $this->client_secret = $consumer_secret;
      $this->token = $oauth_token;
  }


  /**
   * Get the authorize URL
   *
   * @returns a string
   */
  function getAuthorizeURL($token, $sign_in_with_linkedin = TRUE) {
    if (is_array($token)) {
      $token = $token['oauth_token'];
    }
    if (empty($sign_in_with_linkedin)) {
      return $this->authorizeURL() . "?oauth_token={$token}";
    } else {
       return $this->authenticateURL() . "?oauth_token={$token}";
    }
  }

  /**
   * Exchange request token and secret for an access token and
   * secret, to sign API calls.
   *
   * @returns array("oauth_token" => "the-access-token",
   *                "oauth_token_secret" => "the-access-secret",
   *                "user_id" => "9436992",
   *                "screen_name" => "abraham")
   */
  function getAccessToken($code,$redirect) {
    $parameters = array();
    $parameters['code'] = $code;
	$parameters['redirect_uri'] = $redirect;
	$parameters['client_id']=  $this->client_id;
	$parameters['client_secret'] = $this->client_secret;
	$parameters['grant_type'] = "authorization_code";
    $request = $this->post($this->accessTokenURL(), $parameters);
    $token = $request->access_token;
    $this->token = $token;
    return $token;
  }

  function getProfile($fields = array()){
	  if(empty($this->token)) return array();
	  $f = empty($fields)?"first-name,last-name,headline,picture-url,email-address,location:(name,country)":implode(",", $fields);
	  $url = "https://api.linkedin.com/v1/people/~:($f)";
	  $params = array("oauth2_access_token"=>  $this->token);
	  return $this->get($url,$params);
  }
  /**
   * GET wrapper for oAuthRequest.
   */
  function get($url, $parameters = array()) {
	if($this->format=="json")
		$parameters["format"] = "json";
    $response = $this->oAuthRequest($url, 'GET', $parameters);
    if ($this->format === 'json' && $this->decode_json) {
      return json_decode($response);
    }
    return $response;
  }
  
  /**
   * POST wrapper for oAuthRequest.
   */
  function post($url, $parameters = array()) {
    $response = $this->oAuthRequest($url, 'POST', $parameters);
    if ($this->format === 'json' && $this->decode_json) {
      return json_decode($response);
    }
    return $response;
  }

  /**
   * DELETE wrapper for oAuthReqeust.
   */
  function delete($url, $parameters = array()) {
    $response = $this->oAuthRequest($url, 'DELETE', $parameters);
    if ($this->format === 'json' && $this->decode_json) {
      return json_decode($response);
    }
    return $response;
  }

  /**
   * Format and sign an OAuth / API request
   */
  function oAuthRequest($url, $method, $parameters) {
    $params = $this->makeParamString($parameters);
    return $this->http($url, $method, $params);
  }
  
  private function makeParamString($params=array()){
	if (empty($params)) return '';
	$pairs = array();
    foreach ($params as $parameter => $value) {
        $pairs[] = $parameter . '=' . $value;
	}
    return implode('&', $pairs);
  }
  
  /**
   * Make an HTTP request
   *
   * @return API results
   */
  function http($url, $method, $postfields = NULL) {
    $this->http_info = array();
    $ci = curl_init();
    /* Curl settings */
    curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
    curl_setopt($ci, CURLOPT_TIMEOUT, $this->timeout);
    curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ci, CURLOPT_HTTPHEADER, array('Expect:'));
    curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
    curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
    curl_setopt($ci, CURLOPT_HEADER, FALSE);

    switch ($method) {
      case 'POST':
        curl_setopt($ci, CURLOPT_POST, TRUE);
        if (!empty($postfields)) {
          curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
        }
        break;
      case 'DELETE':
        curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if (!empty($postfields)) {
          $url = "{$url}?{$postfields}";
        }
		break;
	  case 'GET':
		if (!empty($postfields)) {
		  $url = "{$url}?{$postfields}";
		}
    }
    curl_setopt($ci, CURLOPT_URL, $url);
    $response = curl_exec($ci);
    $this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
    $this->http_info = array_merge($this->http_info, curl_getinfo($ci));
    $this->url = $url;
    curl_close ($ci);
    return $response;
  }

  /**
   * Get the header info to store.
   */
  function getHeader($ch, $header) {
    $i = strpos($header, ':');
    if (!empty($i)) {
      $key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
      $value = trim(substr($header, $i + 2));
      $this->http_header[$key] = $value;
    }
    return strlen($header);
  }
  
  public function setAccessToken($token){
	  $this->token = $token;
  }
}
