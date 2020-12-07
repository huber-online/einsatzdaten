<?php
/**
 * Copyright (c) 4mengroup GmbH, Ramsau 160, A-6284 Ramsau im Zillertal
 * Alle Rechte vorbehalten.
 */


function restcall($request)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,ILL_URL.$request);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); //timeout after 30 seconds
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_USERPWD, ILL_USER.":".ILL_PASS);
    curl_setopt($ch, CURLOPT_ENCODING, "gzip,deflate");
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
    $result=curl_exec ($ch);
    curl_close ($ch);
    return json_decode($result);
}

function logger($message)
{
    $mt = microtime(true);
    $mh = str_pad((int)(($mt - (int)$mt)*10000),4,"0",STR_PAD_RIGHT);

    error_log("[".date('Y-m-d H:i:s',$mt).".".$mh."] ".$message.PHP_EOL,3,LOG_FILE);
}
function mailinfo($subject,$message)
{
    mail('info@huber-online.at',$subject,$message);
}

class Mcache  {

    var $ttl = 300;
    var $memcache = null;

    function __construct()
    {
        $this->memcache = new Memcache;
        $this->memcache->connect('localhost',11211);
        if (!$this->memcache->get("storedvals")) $this->memcache->set("storedvals",array("dummy"),MEMCACHE_COMPRESSED,0);
    }

    function set($key,$object,$ttl=0)
    {
        if ($ttl == 0)
        {
            $ttl = $this->ttl;
        }
        $this->memcache->set($key,$object,MEMCACHE_COMPRESSED,$ttl);
    }

    function get($key,$awaits=null)
    {
        try
        {
            $result = @$this->memcache->get($key);
        }
        catch (exception $e)
        {
            $result = false;
        }

        if (!empty($awaits))
        {
            switch ($awaits)
            {
                case "array":
                    if (!is_array($result)) return false;
                    break;
                case "non_empty_array":
                    if (!is_array($result) || count($result) == 0) return false;
                    break;
                case "numeric":
                    if (!is_numeric($result)) return false;
                    break;
                case "not_empty":
                    if (empty($result)) return false;
                    break;

            }
        }
        return $result;
    }

    function remove($key)
    {
        try
        {
            @$this->memcache->delete($key);
            return true;
        } catch (Exception $ex) {
            return false;
        }
    }

}
