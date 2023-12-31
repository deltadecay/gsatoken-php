<?php

namespace GSAToken;

interface JWTFactory
{
	// Return a string with the signed JWT token
	public function create($header, $claims, $secretkey);
}

// A cache to hold tokens of the format:
// Array(
//   [access_token] => ya29.c.c0AY.....
//   [expires_in] => 3599
//   [token_type] => Bearer
//   [expires_at] => 1698841702
// )
interface TokenCache
{
	public function isValid($now);
	public function getToken();
	public function load();
	public function save($token);
}

interface HttpClient 
{
	// Perform a http request
	// Should return an array with these fields
	// array("body" => $response, "httpcode" => $httpcode, "headers" => $responseheaders, "error" => $error);
	public function request($method, $url, $headers, $postdata);
}

// Use GoogleServiceAccount to fetch access tokens for use with Google Apis
//
// Example to fetch token for Firebase messaging:
//

//
class GoogleServiceAccount 
{
	const TOKEN_URI = "https://oauth2.googleapis.com/token";

	// The service account configuration
	private $sa = array();
	private $jwtfactory = null;
	private $tokenCache = null;
	private $httpClient = null;


	public function __construct($configFilename = null)
	{
		if ($configFilename == null) {
			throw new \Exception('configFilename is null');
		}
		$this->tokenCache = new DefaultMemoryTokenCache();
		$this->tokenCache->load();
		$this->httpClient = new DefaultHttpClient();
		$this->loadServiceAccountConfig($configFilename);
	}

	public function getJWTFactory()
	{
		return $this->jwtfactory;
	} 

	public function setJWTFactory(JWTFactory $jwtfactory)
	{
		$this->jwtfactory = $jwtfactory;
	}

	public function getTokenCache()
	{
		return $this->tokenCache;
	} 

	public function setTokenCache(TokenCache $tokenCache)
	{
		$this->tokenCache = $tokenCache;
		if ($this->tokenCache) {
			$this->tokenCache->load();
		}
	}

	public function getHttpClient()
	{
		return $this->httpClient;
	} 

	public function setHttpClient(HttpClient $httpClient)
	{
		$this->httpClient = $httpClient;
	}

	public function getServiceAccountConfig()
	{
		return $this->sa;
	}

	public function loadServiceAccountConfig($configFilename)
	{
		$contents = file_get_contents($configFilename);
		$this->sa = json_decode($contents, TRUE);
	}


	// scopes is an array of the scopes to fetch token for. eg. array("https://www.googleapis.com/auth/firebase.messaging") 
	//
	// On success returns an assoc.array with fields:
	// Array
	// (
	//     [access_token] => ya29.c......
	//     [expires_in] => 3599
	//     [token_type] => Bearer
	// )
	//
	// On failure an example of error. See https://developers.google.com/identity/protocols/oauth2/service-account#error-codes
	// Array
	// (
	//     [error] => invalid_grant
	//     [error_description] => Invalid JWT Signature.
	// )
	// returns false if something failed in the network request.
	public function fetchAccessToken($scopes)
	{
		$now = time();

		if ($this->getTokenCache() && $this->getTokenCache()->isValid($now)) {
			return $this->getTokenCache()->getToken();
		}

		if (is_string($scopes)) {
			$scopes = array($scopes);
		}

		$sa = $this->getServiceAccountConfig();
		$oath_token_url = isset($sa['token_uri']) ? $sa['token_uri'] : self::TOKEN_URI;
		// fields used from the service account:
		// token_uri, client_email, private_key_id, private_key
		if (!isset($sa['client_email'])) {
			throw new \Exception('client_email in service account config not set');
		}
		if (!isset($sa['private_key_id'])) {
			throw new \Exception('private_key_id in service account config not set');
		}
		if (!isset($sa['private_key'])) {
			throw new \Exception('private_key in service account config not set');
		}

		# https://developers.google.com/identity/protocols/oauth2/service-account#httprest_1
		# https://developers.google.com/identity/protocols/oauth2/service-account#authorizingrequests
		$header = array("alg" => "RS256", "typ" => "JWT", "kid" => $sa['private_key_id']);
		$claims = array(
			"iss" => $sa['client_email'],
			"scope" => implode(" ", $scopes),
			"aud" => "https://oauth2.googleapis.com/token",
			"exp" => $now + 3600,
			"iat" => $now,
		);
		
		$privkey = $sa['private_key'];

		if (!$this->getJWTFactory()) {
			throw new \Exception('JWT factory not set');
		}
		$jwt = $this->getJWTFactory()->create($header, $claims, $privkey);

		$oauthdata = array(
			"grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
			"assertion" => $jwt,
		);

		$postData = http_build_query($oauthdata);

		$httpHeaders = array("Content-Type: application/x-www-form-urlencoded");
		
		if (!$this->getHttpClient()) {
			throw new \Exception('http client not set');
		}

		$response = $this->getHttpClient()->request("POST", $oath_token_url, $httpHeaders, $postData);
		$responsebody = $response['body'];
		$httpcode = $response['httpcode'];
		$responseheaders = $response['headers'];
		$err = $response['error'];

		//$retryAfterTs = $this->getRetryAfterTimestamp($responseheaders['retry-after'][0]);

		$ret = false;
		if (!$err) {
			$json = json_decode($responsebody, TRUE);
			

			if (!isset($json['error']) && isset($json['access_token']) && isset($json['expires_in'])) {
				// Add a field with a timestamp when the token expires at, decrease by 60 sec to add some margin
				$json['expires_at'] = time() + $json['expires_in'] - 60;

				if ($this->getTokenCache()) {
					$this->getTokenCache()->save($json);
				}	
			}

			if (isset($json['error'])) {
				$json['httpcode'] = $httpcode;
				$json['headers'] = $responseheaders;
			}
			$ret = $json;
		} else {
            $json = json_decode($responsebody, TRUE);
            if ($json === null) {
                $json = array();
            }   
            $json['httpcode'] = $httpcode;
            $json['headers'] = $responseheaders;
            if (!isset($json['error'])) {
                $json['error'] = $err;
            }
            $ret = $json;
		}
		return $ret;
	}

	private function getRetryAfterTimestamp($retryAfter, $now = null) 
	{
		$ts = false;
		$now = $now !== null ? $now : time();
		if (isset($retryAfter) && $retryAfter != null) {
			if (is_numeric($retryAfter)) {
				// If value is numeric, then it is seconds
				$ts = $now + intval($retryAfter);
			} else {
				// If not, then it is a HTTP date
				$ts = strtotime($retryAfter);
			}
		} 
		return $ts;
	}
}


class DefaultHttpClient implements HttpClient 
{
	public function request($method, $url, $headers = "", $postdata = "") 
	{
		if (is_string($headers) && strlen($headers) > 0) {
			$headers = array($headers);
		}
		if (!is_array($headers)) {
			$headers = array();
		}	
		$opts = array('http' => array(
			"method"  => $method,
			"header"  => implode("\r\n", $headers),
			"content" => $postdata,
			)
		);
		$err = false;
		$context = stream_context_create($opts);
		$response = file_get_contents($url, false, $context);
		if ($response === false) {
			$err = error_get_last()['message'];
		}
		// Get http status code
		preg_match('/([0-9])\d+/', $http_response_header[0], $matches);
		$httpcode = intval($matches[0]);
		// Get header rows
		$respheaders = array();
		foreach ($http_response_header as $hrow) {
			$hparts = explode(':', $hrow, 2);
			if (count($hparts) < 2) { 
				continue;
			}
			$respheaders[strtolower(trim($hparts[0]))][] = trim($hparts[1]);
		}
		return array("body" => $response, "httpcode" => $httpcode, "headers" => $respheaders, "error" => $err);
	}
}


class DefaultMemoryTokenCache implements TokenCache
{
	private $cached_token = null;

	public function getToken()
	{
		return $this->cached_token;
	}

	public function load()
	{
	}

	public function save($token)
	{
		$this->cached_token = $token;
	}

	// Check if the cached token is still valid (not expired)
	public function isValid($now)
	{
		return $this->cached_token!=null && $now < ($this->cached_token['expires_at']);
	}
}
