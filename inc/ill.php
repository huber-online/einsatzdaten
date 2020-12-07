<?php
/**
 * Created by PhpStorm.
 * User: uhh
 * Date: 30.03.2016
 * Time: 12:16
 */
class ill {

    var $src = null;
    var $tracking = false;
    var $diveraalert = false;
    var $statuscodes = array(
        "available_at_station"=>array("code"=>"2","status"=>"available_at_station","display"=>"Frei auf Wache","color"=>"green"),
        "reserved_at_station"=>array("code"=>"2","status"=>"reserved_at_station","display"=>"Frei auf Wache","color"=>"green"),
        "available_via_radio"=>array("code"=>"1","status"=>"available_via_radio","display"=>"Verfügbar über Funk","color"=>"yellow"),
        "reserved_via_radio"=>array("code"=>"1","status"=>"reserved_via_radio","display"=>"Verfügbar über Funk","color"=>"yellow"),
        "alarmed_at_station"=>array("code"=>"","status"=>"alarmed_at_station","display"=>"Alarmiert am Stützpunkt","color"=>"red"),
        "alarmed_via_radio"=>array("code"=>"-","status"=>"alarmed_via_radio","display"=>"Alarmiert über Funk","color"=>"red"),
        "on_the_way"=>array("code"=>"3","status"=>"on_the_way","display"=>"Unterwegs zum Einsatzort","color"=>"orange"),
        "arrived"=>array("code"=>"4","status"=>"arrived","display"=>"Am Einsatzort","color"=>"blue"),
        "arrived_at_event"=>array("code"=>"-","status"=>"arrived_at_event","display"=>"Am Einsatz","color"=>"blue"),
        "to_destination"=>array("code"=>"7","status"=>"to_destination","display"=>"Unterwegs zum Zielort","color"=>"green"),
        "at_destination"=>array("code"=>"8","status"=>"at_destination","display"=>"Am Zielort","color"=>"purple"),
        "stand_by"=>array("code"=>"-","status"=>"stand_by","display"=>"Bereitschaft","color"=>"green"),
        "not_available" =>array("code"=>"6","status"=>"not_available","display"=>"außer Betrieb","color"=>"black"),
        "pause_at_station"=>array("code"=>"-","status"=>"pause_at_station","display"=>"Pause","color"=>"green"),
        "pause_via_radio"=>array("code"=>"-","status"=>"pause_via_radio","display"=>"Pause","color"=>"green")
    );

    function __construct()
    {
        $this->mongo = $GLOBALS["_mongo"];
        $this->collEvent = $this->mongo->getCollection("brevent");
        $this->collRes = $this->mongo->getCollection("brresource");
        $this->collTrackRes = $this->mongo->getCollection("brtrack");
        $this->collTrackEvent = $this->mongo->getCollection("brtrackevent");
        $this->collHandy = $this->mongo->getCollection("phonetrack");
    }
    
    function syncEvent()
    {
        $eventnums = $this->collEvent->distinct("EVENTNUM");
        $eventids = $this->collEvent->distinct("ID");
        $requesttime = (int)time();
        $events = restcall("EVENT?extends=eventresource,eventpos,eventtype,noasdata");
        $keep = array("NAME","FIRSTNAME","TELNUMBER","INFO_TO_RESOURCES","CALLSTREET1","CALLSTREET2","CALLHOUSENUMBER","CALLZIPCODE","CALLCITY","CALLADROBJNAME","CALLINFO_LOCATION","diveraalert","custompos");

        if (is_array($events) && count($events) > 0)
        {
            foreach ($events as $e)
            {
                    $write = false;
                    $doc = json_decode(json_encode($e), true);
                    // alten Datensatz einlesen um Werte zu sichern, da im FINISH Datensatz oft nicht mehr vorhanden
                    $meold = $this->collEvent->findOne(array("ID"=>(int)$e->ID,"EVENTNUM"=>(int)$e->EVENTNUM));
                    if (is_array($meold) && strtolower($meold["STATUS"]) != "finished")
                    {
                        foreach ($keep as $kvar)
                        {
                            if (($doc[$kvar] == "" || $doc[$kvar] === null) && isset($meold[$kvar]) && $meold[$kvar] > "")
                            {
                                $doc[$kvar] = $meold[$kvar];
                            }
                            elseif ($doc[$kvar] > "" && isset($meold[$kvar]) &&  strpos($meold[$kvar],$doc[$kvar]) === false)
                            {
                                $doc[$kvar] = $meold[$kvar]."; ".$doc[$kvar];
                            }
                        }
                    }


                    // Wenn keine Eventdatensatz mit aktuellen Status vorhanden ist, dann Update des Datensatzes
                    $me = $this->collEvent->findOne(array("ID"=>(int)$e->ID,"EVENTNUM"=>(int)$e->EVENTNUM,"STATUSTIME"=>$e->STATUSTIME));
                    if (!is_array($me))
                    {
                        $doc["REQUESTTIMESTAMP"] = $requesttime;
                        $doc["REQUESTTIME"] = date("Y-m-d H:i:s", $doc["REQUESTTIMESTAMP"]);
                        // Konvertierungen
                        $doc["ID"] = (int)$doc["ID"];
                        $doc["EVENTNUM"] = (int)$doc["EVENTNUM"];
                        $doc["STATUSTIMESTAMP"] = (int)strtotime($doc["STATUSTIME"]);
                        if ($doc["IDMAIN"] > "") $doc["IDMAIN"] = (int)$doc["IDMAIN"];
                        if ($doc["IDCASE"] > "" ) $doc["IDCASE"] = (int)$doc["IDCASE"];
                        foreach ($doc["eventpos"] as $i=>$v)
                        {
                            $doc["eventpos"][$i]["ID"] = (int)$doc["eventpos"][$i]["ID"];
                            $doc["eventpos"][$i]["IDEVENT"] = (int)$doc["eventpos"][$i]["IDEVENT"];
                            $doc["eventpos"][$i]["LON"] = (double)$doc["eventpos"][$i]["LON"];
                            $doc["eventpos"][$i]["LAT"] = (double)$doc["eventpos"][$i]["LAT"];
                        }
                        foreach ($doc["eventresource"] as $i=>$v)
                        {
                            $doc["eventresource"][$i]["ID"] = (int)$doc["eventresource"][$i]["ID"];
                            $doc["eventresource"][$i]["IDEVENT"] = (int)$doc["eventresource"][$i]["IDEVENT"];
                            $doc["eventresource"][$i]["IDRESOURCE"] = (int)$doc["eventresource"][$i]["IDRESOURCE"];
                            $doc["eventresource"][$i]["STATUSTIMESTAMP"] = (int)strtotime($doc["eventresource"][$i]["STATUSTIME"]);
                        }
                        $doc["eventpos"] = array_values($doc["eventpos"]);
                        $doc["eventtype"] = $doc["eventtype"];
                        $doc["eventresource"] = array_values($doc["eventresource"]);
                        $doc["noasdata"] = $doc["noasdata"];
                        $this->collEvent->update(array("ID"=>(int)$e->ID,"EVENTNUM"=>(int)$e->EVENTNUM),$doc,array("upsert"=>true));
                    }
                    else
                    {
                        // TIMESTAMP CHECK
                        $tscheck = false;
                        if (!isset($me["STATUSTIMESTAMP"]) && isset($me["STATUSTIME"]))
                        {
                            $this->collEvent->update(array("ID"=>(int)$e->ID,"EVENTNUM"=>(int)$e->EVENTNUM),array('$set'=>array("STATUSTIMESTAMP"=>(int)strtotime($me["STATUSTIME"]))),array("upsert"=>true));
                            $doc["STATUSTIMESTAMP"] = (int)strtotime($me["STATUSTIME"]);
                        }
                        $check = false;
                        foreach ($keep as $kvar)
                        {
                            if ($me[$kvar] === null)
                            {
                                $check = true;
                            }
                        }
                        if (strtolower($me["STATUS"]) != "finished")
                        {
                            $check = true;
                        }
                        if ($check)
                        {
                            $check = false;
                            $mev = $this->collTrackEvent->find(array("ID"=>(string)$e->ID,"EVENTNUM"=>(string)$e->EVENTNUM))->sort(array("_id"=>1))->limit(1);
                            if ($mev->hasNext())
                            {
                                foreach ($mev as $mevrec)
                                {
                                    foreach ($keep as $kvar)
                                    {

                                        if (($doc[$kvar] === null || $doc[$kvar] == "") && isset($mevrec[$kvar]) && $mevrec[$kvar] > "")
                                        {
                                            $doc[$kvar] = $mevrec[$kvar];
                                            $check = true;
                                        }
                                        elseif ($doc[$kvar] > "" && isset($mevrec[$kvar]) &&  strpos($mevrec[$kvar],$doc[$kvar]) !== 0)
                                        {
                                            $doc[$kvar] = $mevrec[$kvar]."; ".$doc[$kvar];
                                            $check = true;
                                        }
                                    }
                                }
                                if ($check || strtolower($me["STATUS"]) != "finished" || !isset($me["noasdata"]) && isset($doc["noasdata"]))
                                {
                                    if (!isset($doc["STATUSTIMESTAMP"]) && isset($doc["STATUSTIME"]))
                                    {
                                        $doc["STATUSTIMESTAMP"] = (int)strtotime($doc["STATUSTIME"]);
                                    }
                                    if (!isset($me["noasdata"]) && isset($doc["noasdata"]))
                                    {
                                        $doc["noasdata"] = $doc["noasdata"];
                                    }
                                    $doc["ID"] = (int)$doc["ID"];
                                    $doc["EVENTNUM"] = (int)$doc["EVENTNUM"];
                                    $this->collEvent->update(array("ID"=>(int)$e->ID,"EVENTNUM"=>(int)$e->EVENTNUM),array('$set'=>$doc),array("upsert"=>true));
                                }
                            }
                        }
                    }
                    // Wenn Datensatz mit aktuellem Status existiert, aber die Infofelder leer sind, dann Prüfung ob getrackter Eventdatensatz vorliegt, der Daten enthält
                    if (strtolower($e->STATUS)  != "finished")
                    {
                        $this->tracking = true;
                        if ($e->NAMEEVENTTYPE != "ALP-AD")
                        {
                            foreach ($doc["eventresource"] as $v)
                            {
                                if (preg_match("/Ortsstelle|Einsatzleiter/", $v["NAME_AT_ALARMTIME"]))
                                {
                                    $this->diveraalert = true;
                                    $div = $this->collEvent->findOne(array("EVENTNUM"=>(int)$e->EVENTNUM,"diveraalert"=>array('$ne'=>'fired')));
                                    if (is_array($div))
                                    {
                                        $this->collEvent->update(array("ID" => (int)$e->ID, "EVENTNUM" => (int)$e->EVENTNUM), array('$set' => array('diveraalert' => 'pending')));
                                    }
                                }
                            }
                        }
                    }
            }
        }
    }

    function syncResource()
    {
        $res = restcall("RESOURCES");
        $resources = array();
        $requesttime = (int)time();
        foreach($res as $i=>$d)
        {
            if (preg_match("/^BR HFG/i",$d->TYPE) || preg_match("/^BR ZENTRALE/i",$d->TYPE)  || preg_match("/^BR Fahrzeuge/i",$d->TYPE))
            {
                if (strtolower($d->STATUS)  != "inactive" && strtolower($d->STATUS) != "not_available")
                {
                    $write = false;
                    $doc = json_decode(json_encode($d), true);
                    $me = $this->collRes->findOne(array("CALL_SIGN" => $d->CALL_SIGN, "ID" => (int)$d->ID, "LON" => (double)$d->LON, "LAT" => (double)$d->LAT));
                    if (!is_array($me))
                    {
                        $doc["REQUESTTIMESTAMP"] = $requesttime;
                        $doc["REQUESTTIME"] = date("Y-m-d H:i:s", $doc["REQUESTTIMESTAMP"]);
                        $doc["STATUSTIMESTAMP"] = (int)strtotime($doc["STATUSTIME"]);
                        $doc["ID"] = (int)$doc["ID"];
                        $doc["LAT"] = (double)$doc["LAT"];
                        $doc["LON"] = (double)$doc["LON"];
                        $this->collRes->update(array("CALL_SIGN" => $d->CALL_SIGN, "ID" => (int)$d->ID), $doc, array("upsert" => true));
                    }
                }
            }
        }
    }
    function removeEventPos($id,$lat,$lng)
    {
        if ($id == "" || $lat == "" || $lng == "") return false;

        $ev = $this->collEvent->findOne(array("ID"=>(int)$id));
        if (is_array($ev))
        {
            $event = json_decode(json_encode($ev));
            if (isset($event->custompos) && is_array($event->custompos) && count($event->custompos) > 0)
            {
                $pos = new stdClass();
                $pos->LAT = (float)$lat;
                $pos->LON = (float)$lng;
                foreach ($event->custompos as $k => $p)
                {
                    if ($p->LAT == $pos->LAT && $p->LON == $pos->LON)
                    {
                        unset($event->custompos[$k]);
                    }
                }
                $this->collEvent->update(array("ID" => (int)$id), array('$set' => array("custompos" => array_values($event->custompos))));
                return true;
            }
        }
        return false;
    }

    function setEventPos($id,$lat,$lng,$name="")
    {
        if ($id == "" || $lat == "" || $lng == "") return false;

        $ev = $this->collEvent->findOne(array("ID"=>(int)$id));
        if (is_array($ev))
        {
            $event = json_decode(json_encode($ev));
            if (!isset($event->custompos) || !is_array($event->custompos))
            {
                $event->custompos = array();
            }
            $pos = new stdClass();
            $pos->LAT = (float)$lat;
            $pos->LON = (float)$lng;
            if ($name > "") $pos->ADDROBJNAME = $name;
            $event->custompos[] = $pos;
            $this->collEvent->update(array("ID"=>(int)$id),array('$set'=>array("custompos"=>$event->custompos)));
            return true;
        }
        return false;
    }

    function getEvent($id,$resid=null)
    {
        $ev = $this->collEvent->findOne(array("ID"=>(int)$id));
        if (is_array($ev))
        {
            $event = json_decode(json_encode($ev));
            if (isset($event->eventpos))
            {
                $event->mapcenter = new stdclass;

                if (count($event->eventpos) > 1 && $event->eventpos[1]->LAT !== null && $event->eventpos[1]->LON !== null)
                {
                    if ($GLOBALS["config"]["bounds"]["north"] < $event->eventpos[1]->LAT || $GLOBALS["config"]["bounds"]["south"] > $event->eventpos[1]->LAT || $GLOBALS["config"]["bounds"]["west"] > $event->eventpos[1]->LON || $GLOBALS["config"]["bounds"]["east"] < $event->eventpos[1]->LON)
                    {
                        // Ziel ist ausserhalb
                        $event->mapcenter->lat = $event->eventpos[0]->LAT;
                        $event->mapcenter->lng = $event->eventpos[0]->LON;
                    }
                    else
                    {
                        $event->mapcenter->lat = ($event->eventpos[0]->LAT + $event->eventpos[1]->LAT) / 2;
                        $event->mapcenter->lng = ($event->eventpos[0]->LON + $event->eventpos[1]->LON) / 2;
                    }
                }
                else
                {
                    $event->mapcenter->lat = $event->eventpos[0]->LAT;
                    $event->mapcenter->lng = $event->eventpos[0]->LON;
                }
            }

            if (strtolower($event->STATUS) != "finished")
            {
                // Einsatz läuft noch
                $checktime = date("Y-m-d H:i:s");
            }
            else
            {
                $checktime = $event->STATUSTIME;
            }

            $event->withTracks = false;

            if (isset($event->eventresource) && is_array($event->eventresource) && count($event->eventresource) > 0)
            {
                foreach ($event->eventresource as $evrkey => $evrdata)
                {
                    $tr = $this->collTrackRes->findOne(array("TYPE"=>array('$ne'=>'BR Zentrale'),"ID"=>(string)$evrdata->IDRESOURCE,"REQUESTTIME"=>array('$gte'=>$event->ALARMTIME,'$lte'=>$checktime)));
                    if (is_array($tr))
                    {
                        $event->eventresource[$evrkey]->tracked = true;
                        $event->withTracks = true;
                    }
                }
            }

            // Smartphone Tracking
            $handy = $this->collHandy->distinct("CALL_SIGN",["ID"=>(int)$id]);
            if (!empty($handy))
            {
                $event->withTracks = true;
                foreach ($handy as $h)
                {
                    $event->smartphones[] = (object)["CALL_SIGN"=>$h];
                }
            }

            if ($resid !== null)
            {
                $smartphone = false;
                if (is_numeric($resid))
                {
                    $tr = $this->collTrackRes->find(array("ID" => (string)$resid, "REQUESTTIME" => array('$gte' => $event->ALARMTIME, '$lte' => $checktime)))->sort(array("REQUESTTIMESTAMP" => 1));
                }
                else
                {
                    $smartphone = true;
                    $tr = $this->collHandy->find(array("ID"=>(int)$id,"CALL_SIGN" => (string)$resid))->sort(array("REQUESTTIMESTAMP" => 1));
                }
                if ($tr->hasNext())
                {
                    $lastlng = null;
                    $lastlat = null;
                    $lastdeltalat = 0;
                    $lastdeltalng = 0;
                    $laststate = "";
                    $deltastate = "";
                    $lastcolor = "";
                    $pindex = 0;
                    $dvmax = 0;
                    $dvavg = 0;
                    $dsmax = 0;
                    $dsavg = 0;
                    $sumlat = 0;
                    $sumlng = 0;
                    $latmin = $latmax = $lngmin = $lngmax = null;
                    foreach ($tr as $pt)
                    {
                        if ($smartphone) $pt["STATUS"] = "on_the_way";
                        $point[$pindex] = $pt; // Speicherung des gesamten Verlaufs als spätere Verarbeitungsbasis
                        $latscale[$pindex] = (double)$pt["LAT"];
                        $lngscale[$pindex] = (double)$pt["LON"];
                        $tscale[$pindex] = $pt["REQUESTTIMESTAMP"];
                        if ($pindex > 0)
                        {
                            // Delta Weg
                            $dss = $this->abstand($latscale[$pindex],$lngscale[$pindex],$latscale[$pindex - 1],$lngscale[$pindex - 1]);
                            if ($dss > $dsmax) $dsmax = $dss;
                            $ds[] = $dss;
                            $dsavg += $dss;
                            // Geschwindigkeit
                            $dvv = ($dss/($tscale[$pindex] - $tscale[$pindex - 1]))*60;
                            if ($dvv > $dvmax) $dvmax = $dvv;
                            $dv[] = $dvv;
                            $dvavg += $dvv;
                            // Breite
                            $sumlat += $pt["LAT"];
                            if ($latmax === null || $pt["LAT"] > $latmax) $latmax = $pt["LAT"];
                            if ($lngmax === null || $pt["LON"] > $lngmax) $lngmax = $pt["LON"];
                            if ($latmin === null || $pt["LAT"] < $latmin) $latmin = $pt["LAT"];
                            if ($lngmin === null || $pt["LON"] < $lngmin) $lngmin = $pt["LON"];
                            $lat[] = $pt["LAT"];
                            // Länge
                            $sumlng += $pt["LON"];
                            $lng[] = $pt["LON"];
                        }
                        ++$pindex;
                    }
                    sort ($dv);
                    sort ($ds);

                    $dvavg = $dvavg / count($dv);
                    $dsavg = $dsavg / count($dv);

                    $latavg = $sumlat / count($lat);
                    $lngavg = $sumlng / count($lng);

                    $q3 = 3 * $dv[3*ceil(count($dv)/4)];
                    $qs = 3 * $ds[3*ceil(count($ds)/4)];
                    // Berechnung der mittleren Abweichungen
                    $dsdev = 0;
                    $dvdev = 0;
                    foreach ($ds as $pt)
                    {
                        $dsdev += ($pt - $dsavg) ** 2;
                    }
                    $dsvar = $dsdev / count($ds);
                    $dsdev = sqrt($dsvar);
                    foreach ($dv as $pt)
                    {
                        $dvdev += ($pt - $dvavg) **2;
                    }
                    $dvvar = $dvdev / count($dv);
                    $dvdev = sqrt($dvvar);
                    //$qs = 3 * $dsavg;
                    foreach ($lat as $pt)
                    {
                        $latdev += ($pt - $latavg) ** 2;
                    }
                    $latvar = $latdev / count($lat);
                    $latdev = sqrt($latvar);

                    foreach ($lng as $pt)
                    {
                        $lngdev += ($pt - $lngavg) ** 2;
                    }
                    $lngvar = $lngdev / count($lng);
                    $lngdev = sqrt($lngvar);

                    if ($q3 > $dvmax) $q3 = $dvmax;
                    if ($qs > $dsmax) $qs = $dsmax;

                    // kalman Filter
                    $_SESSION["mapFilter"] == 0 ? $outlier = false : $outlier = true;

                    $samples = ceil(count($point)/10);
                    $threshold = 3.5;
                    if (count($point) < $samples) $samples = 1;
                    //error_log("Anzahl ".count($point)." Samples ".$samples);
                    if ($outlier)
                    {
                        for ($outer = 0; $outer < (count($point) - $samples); $outer++)
                        {
                            $abstand = 0;
                            $winkel = 0;
                            $medcount = 0;
                            for ($inner = 0; $inner < ($samples - 1); $inner++)
                            {
                                $index = $outer + $inner;
                                $aa = $this->abstand($point[$index]["LAT"], $point[$index]["LON"], $point[$index + 1]["LAT"], $point[$index + 1]["LON"]);

                                ++$medcount;
                                $abstand += $aa;
                                $winkel += $this->winkel($point[$index]["LAT"], $point[$index]["LON"], $point[$index + 1]["LAT"], $point[$index + 1]["LON"]);

                            }
                            if ($medcount == 0) $medcount = 1;
                            $medabstand = $abstand / $medcount;
                            $medwinkel = $winkel / $medcount;
                            //error_log("Outer $outer Index $index Mittelwerte Abstand $medabstand Winkel $medwinkel");

                            if (($index + 1) < (count($point) - 1))
                            {
                                if ($this->abstand($point[$index]["LAT"], $point[$index]["LON"], $point[$index + 1]["LAT"], $point[$index + 1]["LON"]) > ($threshold * $medabstand))
                                {
                                    //error_log("sampled point $index");
                                    $point[$index + 1]["LAT"] = $point[$index]["LAT"] + cos($medwinkel) * $medabstand;
                                    $point[$index + 1]["LON"] = $point[$index]["LON"] + sin($medwinkel) * $medabstand;
                                }
                            }
                        }
                    }


                    //error_log("dvavg ".round($dvavg,2). " dvmax ".round($dvmax,2)." q3 ".round($q3,2)." dsavg ".round($dsavg,2)." dsmax ".round($dsmax,2)." qs ".round($qs,2));
                    //error_log("latavg = ".$latavg." lngavg = ".$lngavg." latdev ".$latdev." lngdev ". $lngdev." latmin ".$latmin." latmax $latmax lngmin $lngmin lngmax $lngmax");
                    // Nach der Berechnung des oberen Quartils und der resultierenden oberen Grenze
                    // werden die Daten in Schleife so lange durchlaufen, bis keine Werte ausserhalb der Grenzen zu finden sind

//                    $pointsbefore = count($point);
//                    while ($outlier)
//                    {
//                        $outlier = false;
//                        $found = false;
//                        for ($pindex = 0; $pindex < count($point); $pindex++)
//                        {
//                            if ($pindex > 0 && !$found)
//                            {
//                                $dss = $this->abstand($point[$pindex]["LAT"], $point[$pindex]["LON"], $point[$pindex - 1]["LAT"], $point[$pindex - 1]["LON"]);
//                                $tdiff = $point[$pindex]["REQUESTTIMESTAMP"] - $point[$pindex - 1]["REQUESTTIMESTAMP"];
//                                $dvv = ($dss / $tdiff) * 3600/1000;
//                                //if ($dvv > $q3 || ($dss/$tdiff) > $qs)
//                                //if (abs(($dss - $dsavg)/$dsdev) > 3)
//                                if (abs(($point[$pindex]["LAT"] - $latavg)/$latdev) > 2.5 || abs(($point[$pindex]["LON"] - $lngavg)/$lngdev) > 2.5)
//                                {
//
//                                    $found = true;
//                                    unset($point[$pindex]);
//                                    $pindex = count($point) + 1;
//                                    $point = array_values($point);
//                                    $outlier = true;
//                                }
//                            }
//                        }
//                    }
//                    error_log("OUTLIER ".count($point)."/".$pointsbefore." q3 = ".$q3);
                    $pindex = 0;
                    $lasttime = null;

                    foreach ($point as $pt)
                    {
                        if ($lastlng === null)
                        {
                            $deltalng = 0;
                            $deltalat = 0;
                            $deltatime = 0;
                        }
                        else
                        {
                            $deltalat = abs($lastlat - $pt["LAT"]);
                            $deltalng = abs($lastlng - $pt["LON"]);
                            $deltatime = $pt["REQUESTTIMESTAMP"] - $lasttime;
                        }
                        //if ($lastdeltalat == 0 || $deltalat < 100 * $lastdeltalat || $lastdeltalng == 0 || $deltalng < 100 * $lastdeltalng)
                        $deltatime > 0 ? $dvv = ($this->abstand($lastlat,$lastlng,$pt["LAT"],$pt["LON"])/$deltatime)*60 : $dvv = 0;
                        if ($lastdeltalat == null || $dvv <= $q3 || !$outlier || strtolower($event->STATUS) != "finished")
                        {
                                $lastdeltalat = $deltalat;
                                $lastdeltalng = $deltalng;
                                $lastlat = $pt["LAT"];
                                $lastlng = $pt["LON"];
                                $lasttime = $pt["REQUESTTIMESTAMP"];
                                $pt["STATECHANGE"] = 0;
                                if ($deltastate != $pt["STATUS"])
                                {
                                    $pt["STATECHANGE"] = 1;
                                    $deltastate = $pt["STATUS"];
                                    $lastcolor = $this->statuscodes[strtolower($pt["STATUS"])]["color"];
                                }
                                $pt["color"] = $lastcolor;

                                $event->track[] = json_decode(json_encode($pt));

                        }
                        if (strtolower($pt["STATUS"]) != "available_at_station" && $pt["STATUS"] != $laststate)
                        {
                            $laststate = $pt["STATUS"];
                            if (in_array(strtolower($pt["STATUS"]),array_keys($this->statuscodes))  && $pt["LAT"] != 0 && $pt["LON"] != 0)
                            {
                                $sc = $this->statuscodes[strtolower($pt["STATUS"])];
                                $tpos = $sc;
                                $tpos["LAT"] = $pt["LAT"];
                                $tpos["LON"] = $pt["LON"];
                                $tpos["display"] .= "<br/>".date("H:i:s d.m.Y",$pt["REQUESTTIMESTAMP"])." <br/>".$pt["LAT"].",".$pt["LON"];
                                $event->trackpos[] = $tpos;
                            }

                        }
                    }
                }
            }
            return $event;
        }
        return false;
    }


    function getResource($id)
    {
        $res = $this->collRes->find(array("ID"=>(int)$id))->sort(array("STATUSTIMESTAMP"=>-1))->limit(1);
        if ($res->hasNext())
        {
            $resource = $res->getNext();
            $resource = json_decode(json_encode($resource));
            return $resource;
        }
        return false;
    }

    function getAllResources()
    {
        $resource = array();
        $last = $this->collRes->find()->sort(array("REQUESTTIMESTAMP"=>-1))->limit(1);
        if ($last->hasNext())
        {
            $lcur = $last->getNext();
            $requesttime = (int)$lcur["REQUESTTIMESTAMP"];

            $res = $this->collRes->find()->sort(array("CALL_SIGN" => 1));
            if ($res->hasNext())
            {
                foreach ($res as $row)
                {
                    $resource[] = json_decode(json_encode($row));
                }
            }
            return $resource;
        }
        return false;
    }

    function getAllEvents($sort=-1)
    {
        $ev = $this->collEvent->find(array("IDMAIN"=>null))->sort(array("ALARMTIME"=>$sort));
        if ($ev->hasNext())
        {
            foreach ($ev as $row)
            {
                $ds = json_decode(json_encode($row));
                // Subevents vorhanden?
                $evsub = $this->collEvent->find(array('$or'=>array(array("IDMAIN"=>(string)$ds->ID),array("IDMAIN"=>(int)$ds->ID))))->sort(array("ALARMTIME"=>-1));
                if ($evsub->hasNext())
                {
                    foreach ($evsub as $subrow)
                    {
                        $ds->subevent[] = json_decode(json_encode($subrow));
                    }
                }
                $event[] = $ds;

            }
            return $event;
        }
        return false;
    }

    function einlesen($id)
    {
        $event = restcall("EVENT/$id?extends=eventpos,eventresource,eventtype");
        $eventdoc = json_decode(json_encode($event),true);
        $doc = array();
        $doc["EVENTNUM"] = $eventdoc["EVENTNUM"];
        $doc["REQUESTTIMESTAMP"] = (int)time();
        $doc["REQUESTTIME"] = date("Y-m-d H:i:s",$doc["REQUESTTIMESTAMP"]);
        $re = $_brevent->find(array("EVENTNUM"=>$doc["EVENTNUM"]));
        $write = true;
        if ($re->hasNext())
        {
            $e = $re->getNext();
            foreach ($e["MELDUNGEN"] as $m)
            {
                if ($eventdoc["STATUSTIME"] == $m["STATUSTIME"])
                {
                    $write = false;
                }
            }
        }
        if ($write)
        {
            $eventdoc["REQUESTTIME"] = $doc["REQUESTTIME"];
            $eventdoc["REQUESTTIMESTAMP"] = $doc["REQUESTTIMESTAMP"];
            $eventdoc["STATUSTIMESTAMP"] = strtotime($eventdoc["STATUSTIME"]);
            $eventdoc["ALARMTIMESTAMP"] = strtotime($eventdoc["ALARMTIME"]);
            $doc["MELDUNGEN"][] = $eventdoc;
            $_brevent->update(array("EVENTNUM"=>$doc["EVENTNUM"]),$doc,array("upsert"=>true));
        }
    }

    function abstand($lat1,$lon1,$lat2,$lon2)
    {
        $lat = 0.01745*($lat1 + $lat2)/2;
        $dx = 111.3  * cos($lat) * ($lon1 - $lon2);
        $dy = 111.3 * ($lat1 - $lat2);

//        $R = 6378.137; // Radius of earth in KM
//        $dLat = ($lat2 - $lat1) * pi()/ 180;
//        $dLon = ($lon2 - $lon1) * pi() / 180;
//        $a = sin($dLat/2) * sin($dLat/2) + cos($lat1 * pi() / 180) * cos($lat2 * pi() / 180) * sin($dLon/2) * sin($dLon/2);
//        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
//        $d = $R * $c;
        //return $d * 1000; // meters
        return sqrt($dx ** 2 + $dy ** 2);
    }

    function winkel($lat1,$lon1,$lat2,$lon2)
    {
        $phi1 = $lat1 * pi()/180;
        $lamda1 = $lon1 * pi()*180;
        $phi2 = $lat2 * pi()/180;
        $lamda2 = $lon2 * pi()*180;
        return acos(sin($phi1)*sin($phi2)+cos($phi1)*cos($phi2)*cos($lamda2 - $lamda1));
    }
}

function restcall($request)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,ILL_URL.$request);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_USERPWD, ILL_USER.":".ILL_PASS);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
    $result=curl_exec ($ch);
    curl_close ($ch);
    return json_decode($result);
}