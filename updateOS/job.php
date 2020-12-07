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

//$_mongo->setCollection('ortsstellen');

$q = $_os->query();
foreach ($q as $r)
{
    $oss[$r->NAME] = $r;
}


// Einlesen der OS von LT
$illos = restcall('INFO_STATIONS');
foreach ($illos as $os)
{
	if (empty($oss) || !in_array($os->NAME,array_keys($oss)))
	{
	    $os->ID = (int)$os->ID;
		logger('INSERT '.$os->NAME);
		$_os->insert($os);
	}
}
$_run->update(['ID'=>'lastupdate'],['$set'=>['OS'=>(int)time()]],['upsert'=>true]);
logger ("OS Update finished");
logger ("Update Memchache");

$q = $_os->query();
foreach ($q as $r)
{
    $oss[$r->NAME] = $r;
}
$_mc->set('ortsstellen', json_encode($oss));

// Einlesen der Ressourcen
$q = $_res->query();
$dbres = [];
foreach ($q as $i)
{
    $dbres[$i->ID] = empty($i->STATUSTIME) ? 0 : strtotime($i->STATUSTIME);
}

$illres = restcall('RESOURCES');
foreach ($illres as $res)
{
//    if (empty($dbres) || !in_array($res->ID,array_keys($dbres)))
//    {
//        if (empty($dbres[$res->ID]) || strtotime($dbres[$res->STATUSTIME]) < strtotime($res->STATUSTIME))
//        {
            $res->ID = (int)$res->ID;
            $res->IDADDROBJ = (int)$res->IDADDROBJ;
            $res->IDADDROBJ = (int)$res->IDADDROBJ;
            $res->IDADDROBJORG = (int)$res->IDADDROBJORG;
            $res->LAT = (double)$res->LAT;
            $res->LON = (double)$res->LON;
            $_res->update(['ID'=>$res->ID],$res,['upsert'=>true]);
            echo "UPDATE/INSERT ".$res->CALL_SIGN.PHP_EOL;
//        }
//    }
}
$_run->update(['ID'=>'lastupdate'],['$set'=>['RESOURCES'=>(int)time()]],['upsert'=>true]);