<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
include("default.php");

// Collector Laufzahl erhöhen
$_config->update(array("configitem"=>"main"),array('$inc'=>array("collector"=>1)));
$config = readConfig();

// Sync-Intervalle prüfen -> auf Standard setzen falls fehlend
if (!isset($config["lastsync"]) || !isset($config["resync"]))
{
    $_config->update(array("configitem"=>"main"),array('$set'=>array("lastsync"=>$config["collector"],"resync"=>RESYNC_INTERVALL)));
    $config = readConfig();
}

// Wenn Sync Intervall erreicht, Snychronisation starten
if (($config["collector"] - $config["lastsync"]) >= $config["resync"])
{
    $_config->update(array("configitem"=>"main"),array('$set'=>array("lastsync"=>$config["collector"])));
    $config = readConfig();
    echo "Resync ".$config["organisation"]." ... \n";
    //logger("Resync ".$config["organisation"]);

    $ill = new ill();
    $ill->syncEvent();
    if ($ill->tracking)
    {
        if ($config["autotracking"] == 0 && isset($config["divera_access_token"]) && $ill->diveraalert)
        {
            // Start Divera24/7 - Alarm
            //$divera = file_get_contents("https://www.divera247.com/api/alarm?accesskey=".$config["divera_access_token"]."&type=EINSATZ");
        }
        $config["autotracking"] = 1;
    }
    else
    {
        $config["autotracking"] = 0;
    }
    $_config->update(array("configitem"=>"main"),array('$set'=>array("autotracking"=>$config["autotracking"])));
}

if ($config["tracking"] == 1 || $config["autotracking"] == 1)
{
    echo "Tracking aktiv ...\n";
    logger ("Tracking aktiv");

    $data = array();
    $data["resources"] = restcall("RESOURCES");
    $resources = array();
    foreach($data["resources"] as $i=>$d)
    {
        if (!preg_match("/^BR HFG/i",$d->TYPE) && !preg_match("/^BR ZENTRALE/i",$d->TYPE) && !preg_match("/BR Fahrzeuge/i",$d->TYPE))
        {
            unset($data["resources"][$i]);
        }
        else
        {
            $resources[$d->CALL_SIGN] = $d;
        }
    }
    $keys = array_keys($resources);
    natsort($keys);
    $data["resources"] = array();
    foreach ($keys as $key)
    {
        $data["resources"][] = $resources[$key];
    }
    
    foreach ($data["resources"] as $resource)
    {

        $write = false;
        $mongodoc = json_decode(json_encode($resource), true);
        $mongodoc["REQUESTTIMESTAMP"] = (int)time();
        $mongodoc["REQUESTTIME"] = date("Y-m-d H:i:s", $mongodoc["REQUESTTIMESTAMP"]);
        $mongodoc["trackid"] = $config["trackid"];
        echo $mongodoc["CALL_SIGN"];
        $lastcur = $_brtrack->find(array("CALL_SIGN" => $mongodoc["CALL_SIGN"]))->sort(array("REQUESTTIMESTAMP" => -1))->limit(1);
        if ($lastcur->hasNext())
        {
            $last = $lastcur->getNext();
            if ($last["STATUSTIME"] != $mongodoc["STATUSTIME"] || $last["LON"] != $mongodoc["LON"] || $last["LAT"] != $mongodoc["LAT"])
            {
                $write = true;
                echo "updateing";
            }
        }
        else
        {
            // es gibt noch keinen Trackingdatensatz für die Resource => also muss ein initialer Eintrag erfolgen
            $write = true;
        }

        if ($write)
        {
            $_brtrack->insert($mongodoc);
        }
        else
        {
            echo "no change";

        }
        echo "\n";

    }

    $checkdivera = false;

    if ($ill->diveraalert && !empty($config["alarmemail"]) && !empty($config["diverawaitsforemail"]) && $config["diverawaitsforemail"] == 1)
    {
        logger($config["organisation"]." :: Divera - E-Mail Check");

        $alarmed = checkMails();
        if (is_array($alarmed) && count($alarmed) > 0)
        {
            logger($config["organisation"]." :: ".count($alarmed)." Einsatzmails gefunden ");

            $checkdivera = true;
            array_unique($alarmed);
        }
    }
    elseif ($ill->diveraalert)
    {
        logger ($config["organisation"]." :: Divera Alarm vorgesehen / kein Mailcheck");
    }


    $data["events"] = restcall("EVENT?extends=eventresource,eventpos,eventtype");
    foreach ($data["events"] as $event)
    {
        if ($event->EVENTNUM == 17527653) $event->STATUS = "alarmed";
        if (strtolower($event->STATUS) != "finished" || (time() - strtotime($event->STATUSTIME)) < 300)
        {
            $e = json_decode(json_encode($event), true);
            $event->REQUESTTIME = $e["REQUESTTIME"] = date("Y-m-d H:i:s", time());
            $event->REQUESTTIMESTAMP = $e["REQUESTTIMESTAMP"] = (int)time();

            echo "UPDATE event " . $e["EVENTNUM"] . PHP_EOL;
            logger ("UPDATE event " . $e["EVENTNUM"],$e["EVENTNUM"]);

            $_brtrackevent->insert($event);

            // Auslösung DIVERA247 Alarm
            if ($checkdivera)
            {
                logger($config["organisation"]." :: Divera Auslösecheck");

                $act = $_brevent->findOne(["EVENTNUM" => (int)$e["EVENTNUM"], "diveraalert" => "pending"]);
                if (is_array($act) && in_array($e["EVENTNUM"],$alarmed))
                {
                    logger($config["organisation"]." :: Divera Auslösung");

                    $divera = file_get_contents("https://www.divera247.com/api/alarm?accesskey=".$config["divera_access_token"]."&type=EINSATZ");
                    $_brevent->update(["EVENTNUM" => (int)$e["EVENTNUM"], "diveraalert" => "pending"],['$set'=>['diveraalert'=>'fired']]);

                }
            }
        }
    }

}

function checkMails()
{
    global $config;

    $alarmed = [];
    if (!empty($config["alarmemail"]) && $mbox = imap_open("{" . $config["alarmemail"] . "}", $config["emailuser"], $config["emailpassword"]))
    {
        $estr = imap_errors();
        $headers = imap_headers($mbox);
        foreach ($headers as $num => $hstr)
        {
            if (preg_match("/[^0-9]+\s*([0-9]+)\).*leitstelle\@.*Alarm\-Email/", $hstr, $match))
            {
                $hdr = imap_header($mbox, $match[1]);

                $struct = imap_fetchstructure($mbox, $match[1]);
                $info = "";
                foreach ($struct->parts as $structid => $structcontent)
                {
                    if ($structcontent->subtype == 'PLAIN' || $structcontent->subtype == 'HTML')
                    {
                        $body = imap_fetchbody($mbox, $match[1], $structid + 1);
                        $body = utf8_encode(quoted_printable_decode($body));
                        if ($structcontent->subtype == 'HTML')
                        {
                            $body = strip_tags(html_entity_decode($body));
                            $lines = explode("\n",$body);
                            foreach ($lines as $i=>$l)
                            {
                                if (trim($l) == "")
                                {
                                    unset($lines[$i]);
                                }
                                else
                                {
                                    $lines[$i] = trim($l);
                                }
                            }
                            $body = implode(" ",$lines);
                        }
                        if (preg_match("/Einsatznummer\:[^\d]+(\d+)/", $body, $matches))
                        {
                            $alarmed[] = $matches[1];
                        }
                    }
                }
            }
            //echo str_repeat("-",80).PHP_EOL;
        }
    }
    else
    {
        return false;
    }
    return $alarmed;
}

function logger($t,$e=null)
{
    global $_brlog;
    $ltime = time();
    $_brlog->insert(['logtimestamp'=>(int)$ltime,'logdate'=>date('Y-m-d H:i:s',$ltime),'event'=>(int)$e,'message'=>(string)$t]);
}