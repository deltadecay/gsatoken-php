<?php

// A very simple HttpClient that uses curl if available, otherwise file_get_contents
class HttpClient 
{
	public function request($method, $url, $headers = "", $postdata = "") 
	{
		if (is_string($headers) && strlen($headers) > 0) {
			$headers = array($headers);
		}
		if (!is_array($headers)) {
			$headers = array();
		}	
		if (function_exists('curl_version')) {
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
			// Get header rows
			$respheaders = array();
			$opts[CURLOPT_HEADERFUNCTION] = function($curl, $hrow) use (&$respheaders) {
				$len = strlen($hrow);
				$hparts = explode(':', $hrow, 2);
				if (count($hparts) < 2) {
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
			curl_close($curl);
		} else {
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
			if (isset($respheaders['content-encoding'])) {
				foreach ($respheaders['content-encoding'] as $content_encoding)
				{
					$mult_enc = explode(',', $content_encoding);
					foreach ($mult_enc as $enc) {
						$enc = strtolower(trim($enc));
						if ($enc == "gzip") {
							$response = gzdecode($response);
						} elseif ($enc == "deflate") {
							$response = zlib_decode($response);
						}
					}
				}
			}

		}
		return array("body" => $response, "httpcode" => $httpcode, "headers" => $respheaders, "error" => $err);
	}

}

