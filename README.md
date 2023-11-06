# Google Service Account - fetching an access token

Sample code in *test.php* to fetch an access token for scope firebase messaging.
For this sample to work you need a Google service account configuration file and name the file 
*serviceaccount.json*.
The fields used from the service account file are: token_uri, client_email, private_key_id, private_key.

Tested in php 5.6 and 8.2

## Custom JWTFactory

Implement the interface *\GSAToken\JWTFactory* to use your own methods of creating a signed JWT. Note! It must support RS256 signed JWT as this is what Google expects.

## Custom Http client

Implement the interface *\GSAToken\HttpClient* to use your own http client to perform the request.
An included implementation in class *DefaultHttpClient* is used by default. It uses *curl* if available, otherwise *file_get_contents*.

## Custom token cache

Implement the interface *\GSAToken\TokenCache* to cache the access token. The included *FileTokenCache* shows an implementation where the token is encrypted and stored in a file on disk. For example, you may want to store it in a database.

