<?php
error_reporting(E_ALL);
include("../inc/funclib.php");
include("../inc/mongo.php");
include("../inc/config.php");

empty($argv[1]) ? die() : $action = $argv[1];
empty ($argv[2]) ? $vos = null : $vos = $argv[2];
empty($argv[3]) ? $vid = null : $vid = $argv[3];

if ($action == "list")
{
    if (empty($vid)) $vid = 10;
    $query = [];
    if (!empty($vos)) $query['OSNAME'] = new MongoDB\BSON\Regex ($vos,'i');
    $cur = $_osevent->query($query,['sort'=>['EVENTDATE'=>-1],'limit'=>(int)$vid]);
    $res = iterator_to_array($cur);
    foreach ($res as $e)
    {
        echo $e->EVENTDATE."    ".$e->IDEVENT."    ".$e->OSNAME.PHP_EOL;
    }
}

if ($action == "event")
{
    if (empty($vos)) die();

    $e = $_event->findOne(['ID'=>(int)$vos]);
    if (!empty($e->IDMAIN)) $m = $_event->findOne(['ID'=>(int)$e->IDMAIN]);

    echo $e->ID."/".$e->EVENTNUM.PHP_EOL.$e->ALARMTIME.PHP_EOL;
    foreach ($e->eventresource as $res)
    {
        echo "    ".$res->TIME_ALARM."   ".$res->NAME_AT_ALARMTIME."   ";
        if (preg_match("/\s(OST|Ortsstelle)$/",$res->NAME_AT_ALARMTIME)) echo "OS-Schleife";
        if (preg_match("/\s(EL|Einsatzleiter)$/",$res->NAME_AT_ALARMTIME)) echo "EL-Schleife";
        if (preg_match("/\sZentrale/",$res->NAME_AT_ALARMTIME)) echo "Zentrale";
        echo PHP_EOL;
    }
}