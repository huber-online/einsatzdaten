<?php
/**
 * Copyright (c) 4mengroup GmbH, Ramsau 160, A-6284 Ramsau im Zillertal
 * Alle Rechte vorbehalten.
 */

require ('../vendor/autoload.php');
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

include("../inc/config.php");
include("../inc/funclib.php");
include("../inc/mongo.php");

$client = DynamoDbClient::factory([
    'profile' => 'default',
    'region'   => AWS_REGION,
    'version'  => 'latest',
    'DynamoDb' => [
        'region' => AWS_REGION
    ],
    'credentials.cache' => true,
    'validation'	=> false,
    'scheme' 	=> 'http'
]);

$marshaler = new Marshaler();

// Events von LT holen
$t0 = microtime(true);
$events = restcall("EVENT?extends=eventresource,eventpos,eventtype,noasdata");
echo microtime(true) - $t0;
//print_r($events);

// Einlesen des letzten Events
$resp = $client->scan(['TableName'=>'ortsstellen']);
if (!empty($resp['Items']) && count($resp['Items']) > 0)
{
    foreach ($resp['Items'] as $os)
    {
        $osx = $marshaler->unmarshalItem($os);
        $oss[$osx["NAME"]] = $osx;
    };
}

// Einlesen der OS von LT
$illos = restcall('INFO_STATIONS');
foreach ($illos as $os)
{
	if (!in_array($os->NAME,array_keys($oss)))
	{
		logger('INSERT '.$os->NAME);
		$client->putItem(['TableName'=>'ortsstellen','Item'=>$marshaler->marshalJson(json_encode($os))]);
	}
}
*/
logger ("Event Update finished");
