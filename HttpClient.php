<?php

if (!defined('CURL_HTTP_VERSION_2_0')) 
{
	define('CURL_HTTP_VERSION_2_0', 3);
}

// A very simple HttpClient that uses curl if available, otherwise file_get_contents
//
// Example
//
// $httpClient = new HttpClient();
// $response = $httpClient->request("GET", "https://httpbin.org/uuid");
// $json = json_decode($response['body'], true);
//
class HttpClient 
{
	public function request($method, $url, $headers = "", $data = "") 
	{
		if (is_string($headers) && strlen($headers) > 0) 
		{
			$headers = [$headers];
		}
		if (!is_array($headers)) 
		{
			$headers = [];
		}	
		if (function_exists('curl_version')) 
		{
			$hashttp2 = curl_version()['features'] & CURL_HTTP_VERSION_2_0;
			$opts = [
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => $hashttp2 ? CURL_HTTP_VERSION_2_0 : CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => $method,
				CURLOPT_POSTFIELDS => $data,
				CURLOPT_HTTPHEADER => $headers,
			];
			// Get header rows
			$respheaders = [];
			$opts[CURLOPT_HEADERFUNCTION] = function($curl, $hrow) use (&$respheaders) 
			{
				$len = strlen($hrow);
				$hparts = explode(':', $hrow, 2);
				if (count($hparts) < 2) 
				{
					return $len;
				}
				$respheaders[strtolower(trim($hparts[0]))][] = trim($hparts[1]);
				return $len;
			};
			$curl = curl_init();
			curl_setopt_array($curl, $opts);
			$response = curl_exec($curl);
			$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			$err = curl_error($curl);
			if (PHP_VERSION_ID < 80000) 
			{
				// In 8.0 this is no-op and deprecated in 8.5
				curl_close($curl);
			}
		} 
		else 
		{
			$opts = ['http' => [
				"method"  => $method,
				"header"  => implode("\r\n", $headers),
				"content" => $data,
				"protocol_version" => "1.1",
				//"ignore_errors" => true,
				]
			];
			$err = false;
			$context = stream_context_create($opts);
			$response = file_get_contents($url, false, $context);
			if ($response === false) 
			{
				$err = error_get_last()['message'];
			}
			if (PHP_VERSION_ID >= 80400) 
			{
				// Use function in 8.4 and http_response_header deprecated in 8.5
				$http_last_response_header = http_get_last_response_headers();
				if ($http_last_response_header === null) 
				{
					$http_last_response_header = [];
				}
			} 
			else 
			{
				$http_last_response_header = $http_response_header;
			}
			$httpcode = 0;
			if (count($http_last_response_header) > 0)
			{
				// Get http status code
				if (preg_match('/([0-9])\d+/', $http_last_response_header[0], $matches) > 0)
				{
					$httpcode = intval($matches[0]);
				}
			}
			// Get header rows
			$respheaders = [];
			foreach ($http_last_response_header as $hrow) 
			{
				$hparts = explode(':', $hrow, 2);
				if (count($hparts) > 1) 
				{ 
					$respheaders[strtolower(trim($hparts[0]))][] = trim($hparts[1]);
				}
			}
			if (isset($respheaders['content-encoding'])) 
			{
				foreach ($respheaders['content-encoding'] as $content_encoding)
				{
					$mult_enc = explode(',', $content_encoding);
					foreach ($mult_enc as $enc) 
					{
						$enc = strtolower(trim($enc));
						if ($enc == "gzip") 
						{
							$response = gzdecode($response);
						} 
						elseif ($enc == "deflate") 
						{
							$response = zlib_decode($response);
						}
					}
				}
			}

		}
		return ["body" => $response, "httpcode" => $httpcode, "headers" => $respheaders, "error" => $err];
	}

}


function http_get($url, $headers = "", $data = "")
{
    $httpClient = new HttpClient();
    $response = $httpClient->request("GET", $url, $headers, $data);
    return $response;
}

function http_post($url, $headers = "", $data = "")
{
    $httpClient = new HttpClient();
    $response = $httpClient->request("POST", $url, $headers, $data);
    return $response;
}

