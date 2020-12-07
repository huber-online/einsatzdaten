<?php
/**
 * Copyright (c) 4mengroup GmbH, Ramsau 160, A-6284 Ramsau im Zillertal
 * Alle Rechte vorbehalten.
 */

error_reporting(E_ALL);
include("../inc/funclib.php");
include("../inc/mongo.php");
include("../inc/config.php");


logger ("Starte Verarbeitung ELS Daten");

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

// Vorbereitungen

$requesttime = (int)time();
$eventnums = $_event->distinct('EVENTNUM');
$eventids = $_event->distinct('ID');



// Events von LT holen
$events = restcall("EVENT?extends=eventresource,eventpos,eventtype,noasdata&limit=100&by=ID&order=desc");

if (empty($events))
{
    logger ('Fehler bei Abfrage der ILL-Daten');
    exit();
}

//echo "Events: ".count($events).PHP_EOL;
foreach ($events as $e)
{
    $e->ID = (int)$e->ID;
    $e->EVENTNUM = (int)$e->EVENTNUM;
    $e->IDMAIN = (int)$e->IDMAIN;
    $e->IDCASE = (int)$e->IDCASE;
    if (!empty($e->eventresource))
    {
        foreach ($e->eventresource as $rk=>$r)
        {
            $e->eventresource[$rk]->ID = (int)$r->ID;
            $e->eventresource[$rk]->IDRESOURCE = (int)$r->IDRESOURCE;
            $e->eventresource[$rk]->IDEVENT = (int)$r->IDEVENT;
            $e->eventresource[$rk]->IDADDROBJ = (int)$r->IDADDROBJ;
        }
    }
    if (!empty($e->eventpos))
    {
        foreach ($e->eventpos as $rk=>$r)
        {
            $e->eventpos[$rk]->ID = (int)$r->ID;
            $e->eventpos[$rk]->IDEVENT = (int)$r->IDEVENT;
            $e->eventpos[$rk]->POS = (int)$r->POS;
            $e->eventpos[$rk]->IDADDROBJ = (int)$r->IDADDROBJ;
            $e->eventpos[$rk]->LAT = (double)$r->LAT;
            $e->eventpos[$rk]->LON = (double)$r->LON;
        }
    }
    if (!empty($e->noasdata))
    {
        $e->noasdata->IDEVENT = (int)$e->noasdata->IDEVENT;
    }

    if (empty($e->IDMAIN))
    {
        $els[$e->ID] = $e;
    }
    $write = true;
    $resChange = false;
    $old = $_event->findOne(['ID'=>$e->ID]);
    if (!empty($old))
    {
        // dynamische Daten sichern
        if (!empty($old->diveraalert)) $e->diveraalert = $old->diveraalert;
        if (!empty($old->alarmTyp)) $e->alarmTyp = $old->alarmTyp;

        $write = false;
        // hat sich die STATUSTIME geändert?
        if ($old->STATUSTIME != $e->STATUSTIME)
        {
            $write = true;
        }
        else
        {
            $oldres = [];
            // Neue Resourcen hinzugekommen ?
            if (!empty($old->eventresource))
            {
                foreach ($old->eventresource as $oer)
                {
                    $oldres[] = (int)$oer->ID;
                }
            }
            if (!empty($e->eventresource))
            {
                foreach ($e->eventresource as $cer)
                {
                    if (!in_array((int)$cer->ID, $oldres))
                    {
                        $write = true;
                        $resChange = true;
                    }
                }
            }

            // Neue GeoPositionen hinzugekommen ?
            if (!$write)
            {
                $oldpos = [];
                if (!empty($old->eventpos))
                {
                    foreach ($old->eventpos as $opos)
                    {
                        $oldpos[] = $opos->ID;
                    }
                }
                if (!empty($e->eventpos))
                {
                    foreach ($e->eventpos as $cpos)
                    {
                        if (!in_array($cpos->ID, $oldpos)) $write = true;
                    }
                }
            }

            // NOASDATEN hinzugekommen
            if (!$write)
            {
                if (empty($old->noasdata) && !empty($e->noasdata))
                {
                    $write = true;
                }
            }
        }
    }
    // Einsatz zu Ortsstelle zugeordnet
    $oseq = $_osevent->query(['IDEVENT'=>$e->ID]);
    $ose = iterator_to_array($oseq);
    if (empty($ose) || $resChange)
    {
        if (!empty($e->eventresource))
        {
            foreach ($e->eventresource as $er)
            {
                if (!empty($er->IDRESOURCE && $er->IDRESOURCE > 0))
                {
                    $q = $_res->findOne(['ID'=>(int)$er->IDRESOURCE]);
                    if (!empty($q))
                    {
                        if (!empty($os[$q->IDADDROBJ]))
                        {
                            $_osevent->update(['IDEVENT'=>$e->ID,'OS'=>(int)$os[$q->IDADDROBJ]['ID']],['IDEVENT'=>$e->ID,'OS'=>(int)$os[$q->IDADDROBJ]['ID'],'OSNAME'=>$os[$q->IDADDROBJ]['NAME'],'EVENTDATE'=>$e->ALARMTIME],['upsert'=>true]);
                        }
                    }
                }
            }
        }
    }

    if ($write)
    {

        $_event->update(['ID'=>$e->ID],$e,['upsert'=>true]);
        logger( "Update Event ".$e->ID);
    }

    // Tracking ?
    if ($e->STATUS != 'finished')
    {
        if (!empty($e->eventresource))
        {
            logger ("Tracking für Event ".$e->ID);
            //echo "Tracking für Event ".$e->ID."\n";
            foreach ($e->eventresource as $r)
            {
                if ((int)$r->IDRESOURCE > 0)
                {
                    $robj = $_res->findOne(['ID' => (int)$r->IDRESOURCE]);
                    if (!empty($robj) && !empty($robj->TYPE) &&  preg_match("/(HFG|Fahrzeug)/", $robj->TYPE))
                    {
                        //print_r($robj);
                        $rpos = restcall('RESOURCES/ID/' . $r->IDRESOURCE);
                        $rpos = $rpos[0];
                        $rpos->REQUESTTIME = date('Y-m-d H:i:s', $requesttime);
                        $rpos->REQUESTTIMESTAMP = $requesttime;
                        $rpos->ID = (int)$rpos->ID;
                        $rpos->IDADDROBJ = (int)$rpos->IDADDROBJ;
                        $rpos->IDADDROBJ = (int)$rpos->IDADDROBJ;
                        $rpos->IDADDROBJORG = (int)$rpos->IDADDROBJORG;
                        $rpos->LAT = (double)$rpos->LAT;
                        $rpos->LON = (double)$rpos->LON;
                        $rpos->IDEVENT = $e->ID;
                        $_track->insert($rpos);
                    }
                }
            }
        }
    }
}

//$resA = ['BR HFG','BR HFG Hundeführer','BR HFG Arzt','BR Fahrzeuge'];
//
//foreach ($resA as $filter)
//{
//    $illres = restcall('RESOURCES/?filter[TYPE][eq]='.urlencode($filter));
//    foreach ($illres as $res)
//    {
//        if (empty($dbres) || !in_array($res->ID, array_keys($dbres)))
//        {
//            if (empty($dbres[$res->ID]) || $dbres[$res->ID] < strtotime($res->STATUSTIME))
//            {
//                $res->ID = (int)$res->ID;
//                $res->IDADDROBJ = (int)$res->IDADDROBJ;
//                $res->IDADDROBJ = (int)$res->IDADDROBJ;
//                $res->IDADDROBJORG = (int)$res->IDADDROBJORG;
//                $res->LAT = (double)$res->LAT;
//                $res->LON = (double)$res->LON;
//                $_res->update(['ID' => $res->ID], $res, ['upsert' => true]);
//                //echo "UPDATE/INSERT " . $res->CALL_SIGN . PHP_EOL;
//            }
//        }
//    }
//}
logger ("Ende ELS-Daten Update");
$_run->update(['ID'=>'lastupdate'],['$set'=>['EVENTS'=>(int)time()]],['upsert'=>true]);
$rtn = $_run->findOne(['ID'=>'lastupdate']);
if (!empty($rtn))
{
    logger("Updated at:  ".date('d.m.Y H:i:s',$rtn->EVENTS));
}

