<?php
/**
 * Copyright (c) 4mengroup GmbH, Ramsau 160, A-6284 Ramsau im Zillertal
 * Alle Rechte vorbehalten.
 */

// LT Tirol
const ILL_USER = "GeneralSolutionsBR";
const ILL_PASS = "m3viqDG45GofiGE1QDd2";
const ILL_URL  = "https://api.leitstelle-tirol.at/";

// Applikation
const LOG_FILE = "../logs/update.log";
const ERR_FILE = "../logs/error.log";

// AWS
const AWS_REGION = "eu-west-1";

// AZURE
//const COSMODB_HOST = 'elsdaten.documents.azure.com';
//const COSMODB_PORT = 10255;
//const COSMODB_DATABASE = 'elsdaten';
//const COSMODB_OPTIONS = ['username'=>'elsdaten','password'=>'2msgP3CQSLA3kxNZNsNHfXCGBdeJeDAnrMZhdeRYAszKidyYWFAANtJsVDHYHNWQLQ75M7qb72QcJMfKCQms9w==','ssl'=>true];
const COSMODB_HOST = 'localhost';
const COSMODB_PORT = 27017;
const COSMODB_DATABASE = 'elsdaten';
const COSMODB_OPTIONS = [];

// MongoDB Connection (Azure CosmosDB)
$_mongo = new Mongo(COSMODB_HOST,COSMODB_PORT,COSMODB_DATABASE,COSMODB_OPTIONS);
$_os = new Mongo();
$_os->setCollection('ortsstellen');
$_event = new Mongo();
$_event->setCollection('events');
$_res = new Mongo();
$_res->setCollection('resources');
$_pos = new Mongo();
$_pos->setCollection('eventpos');
$_eventres = new Mongo();
$_eventres->setCollection('eventresources');
$_run = new Mongo();
$_run->setCollection('runtime');
$_track = new Mongo();
$_track->setCollection('track');
$_osevent = new Mongo();
$_osevent->setCollection('osevents');
$_user = new Mongo();
$_user->setCollection('userdata');
$_osintranet = new Mongo;
$_osintranet->setCollection('osintranet');
$_osoptions = new Mongo;
$_osoptions->setCollection('osoptions');


$q = $_run->query(['ID'=>'lastupdate']);
$r = iterator_to_array($q);
if (empty($r))
{
    $_run->insert(['ID'=>'lastupdate','OS'=>(int)0,'RESOURCES'=>(int)0,'EVENTS'=>(int)0]);
    $q = $_run->query();
    $r = iterator_to_array($q);
}
$_flags =  $r[0];

// Memcache
$_mc = new Mcache();
