# Google Service Account - fetching an access token

Sample code in **test.php** to fetch an access token for scope firebase messaging.
For this sample to work you need a Google service account configuration file and name the file 
**serviceaccount.json**.
The fields used from the service account file are: *token_uri*, *client_email*, *private_key_id*, *private_key*.



## Custom JWTFactory

Implement the interface **\GSAToken\JWTFactory** to use your own methods of creating a signed JWT. Note! It must support RS256 signed JWT as this is what Google expects.

```php
interface JWTFactory
{
	// Return a string with the signed JWT token
	public function create($header, $claims, $secretkey);
}

class JWTCreator implements \GSAToken\JWTFactory
{
    public function create($header, $claims, $secretkey)
    {
        return createJWT($header, $claims, $secretkey);
    }
}
```


## Custom Http client

Implement the interface **\GSAToken\HttpClient** to use your own http client to perform the request.
An included implementation in class **DefaultHttpClient** is used by default. It uses **curl** if available, otherwise **file_get_contents**.

```php
interface HttpClient 
{
	// Should return array($responsebody, $httpcode, $responseheaders, $error)
	public function request($method, $url, $headers, $postdata);
}
```

## Custom token cache

Implement the interface **\GSAToken\TokenCache** to cache the access token. The included **FileTokenCache** shows an implementation where the token is encrypted and stored in a file on disk. For example, you may want to store it in a database.

```php
interface TokenCache
{
	public function isValid($now);
	public function getToken();
	public function load();
	public function save($token);
}
```


# Php 

The implementation has been tested in php 5.6 and 8.2. Two shell scripts are included to run php cli via docker. This way no need to install php locally. You need docker installed and then pull the php images referenced in the files or you may change to any version you want. Then run:

```
$ ./php56-cli.sh test.php
```