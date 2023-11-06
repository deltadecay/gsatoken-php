<?php

// echo base64_encode(openssl_random_pseudo_bytes(32));
define('FIRSTKEY','2UOAWCTfPQqXBeAkH/c894dmbsqtTP4VRNny3czRjMU=');

// echo base64_encode(openssl_random_pseudo_bytes(64));
define('SECONDKEY','3SNAAJRV+mhuS2odAAIp1zWNhgsK/Ycr6hTjfzDyyQBngmOInVAd7axRqWXRC0KPBXmiVgaFfHV6j0Zv+dKHng==');

class FileTokenCache implements \GSAToken\TokenCache
{
	private $cached_token = null;
	private $cache_filename = null;

	private $enc_key = "";
	private $mac_key = "";

	public function __construct($cacheFilename = null)
	{
		$this->cache_filename = $cacheFilename;

		// Use some default passphrases for encryption and hmac
		$this->enc_key = base64_decode(FIRSTKEY); 
		$this->mac_key = base64_decode(SECONDKEY); 
	}

	// Set passphrases for the encryption and hmac when storing the token on disk
	// Eg. Generate keys with
	// enc_key = openssl_random_pseudo_bytes(32);
	// mac_key = openssl_random_pseudo_bytes(64);
	public function setPassphrase($enc_key, $mac_key)
	{
		$this->enc_key = $enc_key;
		$this->mac_key = $mac_key;
	}

	public function getToken()
	{
		return $this->cached_token;
	}

	public function load()
	{
		if($this->cache_filename!=null && is_file($this->cache_filename))
        {
			$data = file_get_contents($this->cache_filename);
			if($data !== FALSE)
			{
				$data = $this->decrypt($data);
				if($data !== FALSE) 
				{
					$this->cached_token = json_decode($data, TRUE);
				}	
			}
			else
			{
				$this->cached_token = null;
			}
		}
	}

	// Save the token to file on disk
	public function save($token)
	{
		$this->cached_token = $token;
		if($this->cache_filename != null && $token != null)
		{
			$data = json_encode($token);
			$data = $this->encrypt($data);
			file_put_contents($this->cache_filename, $data);
		}
	}

	// Check if the cached token is still valid (not expired)
	public function isValid($now)
	{
		return $this->cached_token!=null && $now < ($this->cached_token['expires_at']);
	}

	private function encrypt($data)
	{			
		$method = "aes-256-cbc";    
		$iv_length = openssl_cipher_iv_length($method);
		$iv = openssl_random_pseudo_bytes($iv_length);
				
		$data_encrypted = openssl_encrypt($data, $method, $this->enc_key, OPENSSL_RAW_DATA, $iv); 
		// Note! hmac output is binary, thus the length is half compared to hex output   
		$mac = hash_hmac('sha512', $data_encrypted, $this->mac_key, TRUE);
		$bundle = $iv.$mac.$data_encrypted;
		$output = base64_encode($bundle);    
		return $output;        
	}

	private function decrypt($input)
	{          
		$bundle = base64_decode($input);
		$method = "aes-256-cbc";    
		$iv_length = openssl_cipher_iv_length($method);
					
		$iv = substr($bundle, 0, $iv_length);
		$mac = substr($bundle, $iv_length, 64);
		$data_encrypted = substr($bundle, $iv_length + 64);
					
		$data = openssl_decrypt($data_encrypted, $method, $this->enc_key, OPENSSL_RAW_DATA, $iv);
		$mac_new = hash_hmac('sha512', $data_encrypted, $this->mac_key, TRUE);
			
		if (hash_equals($mac, $mac_new))
		{
			return $data;
		}	
		return false;
	}
}