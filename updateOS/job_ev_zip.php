<?php
/**
 * Copyright (c) 4mengroup GmbH, Ramsau 160, A-6284 Ramsau im Zillertal
 * Alle Rechte vorbehalten.
 */

error_reporting(E_ALL);
include("../inc/funclib.php");
include("../inc/mongo.php");
include("../inc/config.php");


function zipcall($request,$gzip=false)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,ILL_URL.$request);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); //timeout after 30 seconds
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_USERPWD, ILL_USER.":".ILL_PASS);
    if ($gzip) curl_setopt($ch, CURLOPT_ENCODING, "gzip,deflate");
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
    $result=curl_exec ($ch);
    curl_close ($ch);
    return json_decode($result);
}

// Ortsstllen einlesen und in memcache halten
$os = $_mc->get('ortsstellen');
if (empty($os))
{
    $q = $_os->query();
    foreach ($q as $r)
    {
        $oss[$r->NAME] = $r;
        $oss[(int)$r->ID] = $r;
    }
    $_mc->set('ortsstellen', json_encode($oss));
    $os = $_mc->get('ortsstellen');
}
$os = json_decode($os,true);


// Events von LT holen
$t0 = microtime(true);
$events = zipcall("EVENT?extends=eventresource,eventpos,eventtype,noasdata",true);
$t1 = microtime(true);
echo "T1: ".($t1 - $t0)."\n";

$events = zipcall("EVENT?extends=eventresource,eventpos,eventtype,noasdata",false);
$t2 = microtime(true);
echo "T2: ".($t2 - $t1)."\n";
