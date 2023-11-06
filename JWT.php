<?php

function base64url_encode($data)
{
	return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data)
{
	return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen( $data )) % 4 ));
}

// Create a signature for the unsigned JWT. 
// data - string data to be signed
// secretkey  - can either point to file on disk, be a string content of that p8 file, or a string secret for hmac.
// alg - five char string denoting the algorithm, eg. RS256. Must match they secretkey
// The returned signature is raw bytes.
function signJWT($data, $secretkey, $alg)
{
	if(strlen($alg) != 5) 
	{
		throw new \Exception('alg should be five chars, eg. RS256');
	}
	$enc = substr($alg, 0, 2);
	$len = substr($alg, 2);
	
	if($secretkey == null)
	{
		throw new \Exception('secretkey is null');
	}

	if(in_array($enc, array('RS', 'ES')))
	{
		if (!$key = openssl_pkey_get_private($secretkey))
		{
			throw new \Exception('Failed to read secretkey');
		}
		$details = openssl_pkey_get_details($key);

		if (isset($details['rsa']) && $enc != 'RS') 
		{
			throw new \Exception('Provided key is RSA but alg does not specify RS');
		}
		if (isset($details['ec']) && $enc != 'ES') 
		{
			throw new \Exception('Provided key is ECDSA but alg does not specify ES');
		}

		$sign_algo = OPENSSL_ALGO_SHA256;
		if($len == 384) $sign_algo = OPENSSL_ALGO_SHA384;
		if($len == 512) $sign_algo = OPENSSL_ALGO_SHA512;

		if (!openssl_sign($data, $signature, $key, $sign_algo))
		{
			throw new \Exception('Failed to sign JWT');
		}
		if(PHP_VERSION_ID < 80000) {
			// Deprecated in PHP 8.0+ since they are no longer of type resource that need to be freed.
			openssl_pkey_free($key);
		}
	}
	else if($enc == 'HS')
	{
		$sign_algo = 'sha256';
		if($len == 384) $sign_algo = 'sha384';
		if($len == 512) $sign_algo = 'sha512';
		$signature = hash_hmac($sign_algo, $data, $secretkey, true);
	}
	else
	{
		throw new \Exception('Unsupported signing method. Supported HS, RS, ES with 256, 384, 512 hashing.');
	}
	return $signature;
}

function createJWT($header, $claims, $secretkey, $alg = null)
{
	$header_str = json_encode($header);
	$claims_str = json_encode($claims);
	$unsigned_jwtoken = base64url_encode($header_str).".".base64url_encode($claims_str);
	// Use specified alg, otherwise from header
	$sign_alg = $alg != null && strlen($alg) > 0 ? $alg : $header['alg'];
	$signature_rawbytes = signJWT($unsigned_jwtoken, $secretkey, $sign_alg);
	$signature = base64url_encode($signature_rawbytes);
	$signed_jwtoken = $unsigned_jwtoken.".".$signature;
	return $signed_jwtoken;
}

// Get the value of a the specified field from the Claims section of the JWT
function getJWTClaimsField($jwt, $field)
{
	$parts = explode(".", $jwt);
	$claims_str = base64url_decode($parts[1]);
	$claims = json_decode($claims_str, TRUE);
	return $claims[$field];
}

