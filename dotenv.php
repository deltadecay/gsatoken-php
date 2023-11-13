<?php 

function dotenv($envfile = ".env", $line_sep="\n")
{
    $contents = "$envfile";
    if(is_file($envfile)) {
        $contents = file_get_contents($envfile);
    } 
    $lines = array_filter(explode($line_sep, $contents), function($line) {
        return strlen(trim($line)) > 0;
    });
    $map = array_reduce($lines, function($acc, $line) {
        $parts = explode("=", $line, 2);
        if(count($parts) > 1) {
            $acc[strtoupper(trim($parts[0]))] = trim($parts[1]);
        }
        return $acc;
    }, array());
    return $map;
}

define("DOTENV_STORE_ENV", 1);
define("DOTENV_STORE_SERVER", 2);
define("DOTENV_STORE_PUTENV", 4);

define("DOTENV_STORE_ALL", 7);

function store_dotenv($dotenv, $store=DOTENV_STORE_ENV, $overwrite=false)
{
    foreach($dotenv as $key => $value) {
        if((!isset($_ENV[$key]) || $overwrite) && ($store & DOTENV_STORE_ENV) > 0) {
            $_ENV[$key] = $value;
        }
        if((!isset($_SERVER[$key]) || $overwrite) && ($store & DOTENV_STORE_SERVER) > 0) {
            $_SERVER[$key] = $value;
        }
        if((getenv($key)===false || $overwrite) && ($store & DOTENV_STORE_PUTENV) > 0) {
            putenv("$key=$value");
        }
    }
}