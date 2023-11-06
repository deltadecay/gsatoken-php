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
	// Should return array($responsebody, $httpcode, $error)
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
		if($configFilename == null) 
		{
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
		if($this->tokenCache) 
		{
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

		if($this->getTokenCache() && $this->getTokenCache()->isValid($now))
		{
			return $this->getTokenCache()->getToken();
		}

		if(is_string($scopes))
		{
			$scopes = array($scopes);
		}

		$sa = $this->getServiceAccountConfig();
		$oath_token_url = isset($sa['token_uri']) ? $sa['token_uri'] : self::TOKEN_URI;

		// fields used from the service account:
		// token_uri, client_email, private_key_id, private_key
		if(!isset($sa['client_email']))
		{
			throw new \Exception('client_email in service account config not set');
		}
		if(!isset($sa['private_key_id']))
		{
			throw new \Exception('private_key_id in service account config not set');
		}
		if(!isset($sa['private_key']))
		{
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

		if(!$this->getJWTFactory()) 
		{
			throw new \Exception('JWT factory not set');
		}
        $jwt = $this->getJWTFactory()->create($header, $claims, $privkey);

        $oauthdata = array(
            "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
            "assertion" => $jwt,
            );
    
        $postData = http_build_query($oauthdata);

		$httpHeaders = array("Content-Type: application/x-www-form-urlencoded");
		
		if(!$this->getHttpClient())
		{
			throw new \Exception('http client not set');
		}

		list($response, $httpcode, $err) = $this->getHttpClient()->request("POST", $oath_token_url, $httpHeaders, $postData);

        $token = false;
        if(!$err)
        {
            $token = json_decode($response, TRUE);
			if(isset($token['access_token']) && isset($token['expires_in']))
			{
				// Add a field with a timestamp when the token expires at, decrease by 60 sec to add some margin
				$token['expires_at'] = time() + $token['expires_in'] - 60;
			}
			
			if($this->getTokenCache()) 
			{
				$this->getTokenCache()->save($token);
			}
        }
        return $token;
    }

}


class DefaultHttpClient implements HttpClient 
{
	public function request($method, $url, $headers, $postdata = "") 
	{
		if(is_string($headers))
		{
			$headers = array($headers);
		}
		if(function_exists('curl_version'))
		{
			$opts = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => $method,
				CURLOPT_POSTFIELDS => $postdata,
				CURLOPT_HTTPHEADER => $headers,
			);
			$curl = curl_init();
			curl_setopt_array($curl, $opts);
			$response = curl_exec($curl);
			$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			$err = curl_error($curl);
			curl_close($curl);
		} 
		else 
		{
			$opts = array('http' => array(
				"method"  => $method,
				"header"  => implode("\r\n", $headers),
				"content" => $postdata,
				)
			);
			$context = stream_context_create($opts);
			$response = file_get_contents($url, false, $context);
			preg_match('/([0-9])\d+/', $http_response_header[0], $matches);
			//print_r($http_response_header);
			// Array
			// (
			// 	[0] => HTTP/1.0 200 OK
			// 	[1] => Content-Type: application/json; charset=UTF-8
			// 	[2] => Vary: X-Origin
			// 	[3] => Vary: Referer
			// 	[4] => Date: Mon, 06 Nov 2023 10:18:41 GMT
			// 	[5] => Server: scaffolding on HTTPServer2
			// 	[6] => Cache-Control: private
			// 	[7] => X-XSS-Protection: 0
			// 	[8] => X-Frame-Options: SAMEORIGIN
			// 	[9] => X-Content-Type-Options: nosniff
			// 	[10] => Alt-Svc: h3=":443"; ma=2592000,h3-29=":443"; ma=2592000
			// 	[11] => Accept-Ranges: none
			// 	[12] => Vary: Origin,Accept-Encoding
			// )
			$httpcode = intval($matches[0]);
			$err = ($response === false);
		}
		return array($response, $httpcode, $err);
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