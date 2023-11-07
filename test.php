<?php

// https://developers.google.com/identity/protocols/oauth2/service-account#httprest
require_once("GoogleServiceAccount.php");
require_once("JWT.php");
require_once("FileTokenCache.php");

class JWTCreator implements \GSAToken\JWTFactory
{
    public function create($header, $claims, $secretkey)
    {
        return createJWT($header, $claims, $secretkey);
    }
}


$scopes = array("https://www.googleapis.com/auth/firebase.messaging");

// Config file for a Google Service Account
$configfile = __DIR__."/serviceaccount.json";

// Name of a file where to store the cached token
$cachefile = __DIR__."/accesstoken.cache";

$gsa = new \GSAToken\GoogleServiceAccount($configfile);
$tokencache = new FileTokenCache($cachefile);

// echo base64_encode(openssl_random_pseudo_bytes(32));
// echo base64_encode(openssl_random_pseudo_bytes(64));
$enc_key = base64_decode("sz2D2lR8zPSCRHl/YZcYYBi1xFOW003rtjn5foCG/Qg=");
$mac_key = base64_decode("V7oKzLdgWjHQftXs70sOgzXksuiwHPigf4cLE6qPdic+Y6zrR6pvzN+RqS2K/i2iB2sajvgIElmLGGa3KoLemw==");
$tokencache->setPassphrase($enc_key, $mac_key);
$gsa->setTokenCache($tokencache);
$gsa->setJWTFactory(new JWTCreator());

$now = time();
$ret = $gsa->fetchAccessToken($scopes);
$accesstoken = $ret['access_token'];
$ret['expires_in'] = $ret['expires_at'] > $now ? $ret['expires_at'] - $now : 0;
print_r($ret);

/*
Array
(
    [access_token] => ya29.c.c0AY.....
    [expires_in] => 3599
    [token_type] => Bearer
    [expires_at] => 1698841702
)
*/

exit;
