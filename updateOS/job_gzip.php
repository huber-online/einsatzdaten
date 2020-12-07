<?php
/**
 * Copyright (c) 4mengroup GmbH, Ramsau 160, A-6284 Ramsau im Zillertal
 * Alle Rechte vorbehalten.
 */

require ('../vendor/autoload.php');
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

include("../inc/mongo.php");
include("../inc/funclib.php");
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

//$_mongo->setCollection('ortsstellen');

$q = $_os->query();
foreach ($q as $r)
{
    $oss[$r->NAME] = $r;

}


// Einlesen der OS von LT
$illos = zipcall('INFO_STATIONS',true);

foreach ($illos as $os)
{

	    echo str_replace("\n"," ",print_r($os,true))."\n";

}

echo "\n\n==========================================================================================================================\n\n";

$illos = zipcall('INFO_STATIONS');

foreach ($illos as $os)
{

    echo str_replace("\n"," ",print_r($os,true))."\n";

}