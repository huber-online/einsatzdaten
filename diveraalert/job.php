<?php
/**
 * Copyright (c) 4mengroup GmbH, Ramsau 160, A-6284 Ramsau im Zillertal
 * Alle Rechte vorbehalten.
 */

error_reporting(E_ALL);
include("../inc/funclib.php");
include("../inc/mongo.php");
include("../inc/config.php");


logger ("Starte Verabreitung Divera Alarmierung");


$cur = $_osoptions->query(['diveratoken'=>['$gt'=>'']]);
if (!empty($cur)) {
    foreach ($cur as $os)
    {
        if (empty($os->diveratoken)) continue;

        if (empty($os->noalarm) || $os->noalarm == 0)
        {
            $osdata = $_os->findOne(['ID' => (int)$os->OS]);
            $os->NAME = $osdata->NAME;
            $rics = false;
            $ric_info = $ric_el = $ric_os = null;
            if (!empty($os->diverarics)) {
                if (!empty($os->diverarics->enabled) && $os->diverarics->enabled == 1) {
                    $rics = true;
                    empty($os->diverarics->INFO) ? $ric_info = "" : $ric_info = $os->diverarics->INFO;
                    empty($os->diverarics->ELALARM) ? $ric_el = "" : $ric_el   = $os->diverarics->ELALARM;
                    empty($os->diverarics->OSALARM) ? $ric_os = "" : $ric_os   = $os->diverarics->OSALARM;
                }
            }
            if (empty($os->diveraversion)) $os->diveraversion = "free";

            $infoalarm = false; // INFO Alarmierung
            if (!empty($os->infoalarm) && $os->infoalarm == 1) $infoalarm = true;
            $adalarm = false; // Ambulanzdienst Alarmierung
            if (!empty($os->adalarm) && $os->adalarm == 1) $adalarm = true;
            $OS[] = ['os' => $osdata->NAME, 'ID' => (int)$os->OS, 'diveratoken' => $os->diveratoken, 'diveraversion' => $os->diveraversion,'rics'=>$rics,'ric_info'=>$ric_info,'ric_el'=>$ric_el,'ric_os'=>$ric_os,'infoalarm' => $infoalarm,'adalarm'=>$adalarm];
        }
    }
}


foreach ($OS as $o)
{
    // Check Alarm-Email
    // nicht mehr nötig durch folgende Logik - bei reiner Info an OS sind nur die EL alarmiert, keine Zentrale
    // => Mail-Gegencheck nicht mehr nötig

    $e = $_osevent->query(['OS'=>$o['ID']]);
    $events = iterator_to_array($e);
    $osDisplay = false;
    //if ($o['infoalarm'] || $o['rics'] == true) print_r($o);
    foreach ($events as $akt)
    {
        //mailinfo('Checking '.$akt->OSNAME,print_r($akt,true));
        if (!$osDisplay)
        {
            $osDisplay = true;

            echo "Checking ".$akt->OSNAME."...".PHP_EOL;
        }
        // laufende Einsätze auslesen
        $data = $_event->findOne(['ID'=>(int)$akt->IDEVENT,'STATUS'=>['$ne'=>'finished']]);
        if (!empty($data->ID))
        {
            echo "  laufender Einsatz gefunden\n";
            //mailinfo("Laufdender Einsatz ".$akt->OSNAME,$data->ID."/".$data->EVENTNUM);
            logger("Checking ".$akt->OSNAME);
            logger ("laufender Einsatz gefunden ".$data->ID."/".$data->EVENTNUM);

            // Prüfung ob ein zugehöriger Mainevent vorhanden ist
            $main = null;
            if (!empty($data->IDMAIN))
            {
                $main = $_event->findOne(['ID'=>(int)$data->IDMAIN]);
                if (!empty($main->ID))
                {
                    echo "    Haupteinsatz dazu gefunden\n";
                    logger ("Haupteinsatz gefunden ".$main->ID."/".$main->EVENTNUM);
                }
            }
            else
            {
                echo "    Einsatztyp: Haupteinsatz\n";
                logger("Einsatztyp: Haupteinsatz");
            }

            $subs = Null;
            $subs = $_event->query(['IDMAIN'=>(int)$data->ID]);
            if (!empty($subs))
            {
                foreach ($subs as $sev)
                {
                    echo "    Untereinsatz gefunden ".$sev->ID."\n";
                    logger ("Untereinsatz gefunden ".$sev->ID);
                }
            }
            else
            {
                echo "    keine Untereinsätze\n";
                logger("keine Untereinsätze");
            }

            $toAlarm = false;
            $alarmTyp = "";
            $alarmCheck = [0,0,0];
            // Divera Alarm Feld initialisieren
            if ($data->NAMEEVENTTYPE != 'ALP-AD' || $o['adalarm'] == true)
            {

                foreach ($data->eventresource as $res)
                {
                    if (preg_match("/\s(OST|Ortsstelle)$/", trim($res->NAME_AT_ALARMTIME)))
                    {
                        logger ("Ortstelle alarmiert");
                        $alarmCheck[0] = 1;
                    }
                    if (preg_match("/\s(EL|Einsatzleiter)$/", trim($res->NAME_AT_ALARMTIME)))
                    {
                        logger("Einsatzleiter alarmiert");
                        $alarmCheck[1] = 1;
                    }
                    if (preg_match("/\s(Zentrale)/",trim($res->NAME_AT_ALARMTIME)))
                    {
                        $alarmCheck[2] = 1;
                        logger ("Zentrale alarmiert");
                    }
                }
                // Alarmierung wenn OS alaramiert oder EL und Zentrale
                if ($alarmCheck[0] == 1 || ($alarmCheck[1] == 1 && $alarmCheck[2] == 1))
                {
                    echo "   Alarmierungsvoraussetzung erfüllt\n";
                    //logger ("Alarmierungsvoraussetzung erfüllt");
                    $toAlarm = true;
                    if ($alarmCheck[0] == 1)
                    {
                        $alarmTyp = "OS";
                    }
                    else
                    {
                        $alarmTyp = "EL";
                    }
                }
                // INFO ALARM
                if ($alarmCheck[0] == 0 && $alarmCheck[1] == 1 && $alarmCheck[2] == 0)
                {
                    if ($o['infoalarm'])
                    {
                        $toAlarm = true;
                        $alarmTyp = "INFO";
                    }
                }
                if ($toAlarm) {
                    $log = "Alarmtyp ".$alarmTyp;
                    if (!empty($data->diveraalert)) $log .= " - breits alarmiert";
                    if (!empty($data->alarmTyp))
                    {
                        $log .= " ".$data->alarmTyp;
                        if ($data->alarmTyp != $alarmTyp)
                        {
                            if ($data->diveraalert == "fired")
                            {
                                if (($data->alarmTyp != "OS" && $alarmTyp == "OS") || ($data->alarmTyp == "INFO" && $alarmTyp == "EL"))
                                {
                                    $data->diveraalert = "pending";
                                    $_event->update(['ID' => (int)$data->ID], ['$set' => ['diveraalert' => 'pending']]);
                                }
                            }
                        }
                    }
                    logger($log);

                }
            }

//            if ($toAlarm && in_array($data->EVENTNUM,$alarmed) && (empty($data->diveraalert) || $data->diveraalert != 'fired'))

            if ($toAlarm && (empty($data->diveraalert) || $data->diveraalert != 'fired'))
            {
                echo "   Alarmierung\n";

                if (empty($data->diveraalert)) $_event->update(['ID'=>(int)$data->ID],['$set'=>['diveraalert'=>'pending']]);
                echo "ALARM für EVENT ".$data->EVENTNUM.PHP_EOL;
                logger ("ALARM für EVENT ".$data->ID."/".$data->EVENTNUM);

                $diveracall = "https://www.divera247.com/api/alarm?accesskey=".$o['diveratoken']."&type=EINSATZ";

                if (!empty($o["diveraversion"]) && strtolower($o["diveraversion"]) != "free")
                {


                    $loc = "";
                    $adr = "";
                    $txt = $data->NAMEEVENTTYPE;
                    if (!empty($data->noasdata->ERGEBNIS_RD)) $txt .= " - ".$data->noasdata->ERGEBNIS_RD;
                    if (!empty($data->noasdata->ERGEBNIS_FW)) $txt .= " | ".$data->noasdata->ERGEBNIS_FW;
                    $txt .= "\n\n";

                    if (!empty($data->eventpos[0]))
                    {
                        $pos = $data->eventpos[0];
                        if (!empty($pos->ZIPCODE)) $adr .= $pos->ZIPCODE.' ';
                        if (!empty($pos->CITY)) $adr .= $pos->CITY;
                        $adr .= ", ";
                        if (!empty($pos->STREET1)) $adr .= $pos->STREET1;
                        if (!empty($pos->HOUSENUMBER)) $adr .= ' '.$pos->HOUSENUMBER;
                        $adr .= "\n";
                        if (!empty($pos->ADDROBJNAME)) $adr .= $pos->ADDROBJNAME.', ';
                        if (!empty($pos->INFO_LOCATION)) $adr .= $pos->INFO_LOCATION;
                        //$adr .= "\n\n";
                        $diveracall .= "&address=".urlencode(substr($adr,0,250));
                        if (!empty($pos->LON) && !empty($pos->LAT)) $loc = '&lng='.$pos->LON.'&lat='.$pos->LAT;
                    }
                    if (!empty($data->INFO_TO_RESOURCES)) $txt .= $data->INFO_TO_RESOURCES.', ';
                    if (!empty($data->noasdata->BEMERKUNG)) $txt .= $data->noasdata->BEMERKUNG;
                    $txt .= "\n\n";
                    if (!empty($data->noasdata->ABFRAGE)) $txt .= $data->noasdata->ABFRAGE;
                    if (!empty($data->VORNAME) || !empty($data->NAME) || !empty($data->TELNUMBER))
                    {
                        $txt .= "\n\n";
                        $txt .= "Melder: ";
                        if (!empty($data->VORNAME)) $txt .= $data->VORNAME.' ';
                        if (!empty($data->NAME)) $txt .= $data->NAME;
                        if (!empty($data->TELNUMBER)) $txt .= ', '.$data->TELNUMBER.' ';
                    }
                    $diveracall .= "&text=".urlencode(substr($txt,0,1000));
                    if ($loc != "") $diveracall .= $loc;
                    if ($o['rics'])
                    {
                        $ric = "";
                        switch ($alarmTyp)
                        {
                            case "INFO":
                                $ric = $o['ric_info'];
                                break;
                            case "EL":
                                $ric = $o['ric_el'];
                                break;
                            case "OS":
                                $ric = $o['ric_os'];
                                break;
                        }
                        if ($ric > "")
                        {
                            $diveracall .= "&ric=".$ric;
                            mailinfo($alarmTyp.'-Alarm für '.$akt->OSNAME,$diveracall);
                        }
                    }
                }
                logger ($diveracall);
                $divera = file_get_contents($diveracall);
                logger ($divera);
                $_event->update(["ID" => (int)$data->ID, "diveraalert" => "pending"],['$set'=>['diveraalert'=>'fired','alarmTyp'=>$alarmTyp]]);

            }
        }
    }
}


/*
 *  ALTE EMAIL LOGIK
 *
$alarmed = [];
if ($mbox = imap_open("{".$o['mailserver'].":110/pop3/notls}", $o["mailuser"], $o["mailpass"]))
{
    $estr = imap_errors();
    $headers = imap_headers($mbox);

    $count = imap_num_msg($mbox);
    for($msgno = 1; $msgno <= $count; $msgno++)
    {
        $headstr = imap_headerinfo($mbox, $msgno,0,1024);
        $hstr = $headstr->subject;
        //echo $hstr.PHP_EOL;
        if (preg_match("/Alarm\-Email/", $hstr, $match))
        {
//                echo 'match'.PHP_EOL;
//                $hdr = imap_header($mbox, $match[1]);
//                print_r($hdr);
//                $struct = imap_fetchstructure($mbox, $match[1]);
            $struct = imap_fetchstructure($mbox, $msgno);
            $info = "";
            foreach ($struct->parts as $structid => $structcontent)
            {
                //print_r($structcontent);
                if ($structcontent->subtype == 'PLAIN' || $structcontent->subtype == 'HTML' || $structcontent->subtype = 'ALTERNATIVE')
                {
                    $body = imap_fetchbody($mbox, $msgno, $structid + 1);
                    $body = utf8_encode(quoted_printable_decode($body));
                    if ($structcontent->subtype == 'HTML' || $structcontent->subtype == 'PLAIN')
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
                    } else {
                        foreach ($lines as $i=>$l)
                        {
                            //echo $l.PHP_EOL;
                            if ($l == "Einsatznummer:" && is_numeric($l[$i+1]))
                            {
                                $alarmed[] = $l[$i + 1];
                            }
                        }
                    }
                }
            }
        }
        //echo str_repeat("-",80).PHP_EOL;
    }
//        print_r($alarmed);
    echo " ------------------------".PHP_EOL;
    if (!empty($alarmed) && is_array($alarmed) && count($alarmed) > 0)
    {
        foreach ($alarmed as $a)
        {
//                echo "X-REF Check ".$a.PHP_EOL;
            $adata = $_event->findOne(['EVENTNUM'=>(int)$a]);
            if (!empty($adata->ID))
            {
//                    echo "-- Event found".PHP_EOL;
                // untergeordnet?
                if (!empty($adata->IDMAIN) && $adata->IDMAIN > 0)
                {
//                        echo "-- ist Untereinsatz".PHP_EOL;
                    $he = $_event->findOne(['ID'=>(int)$adata->IDMAIN]);
                    if (!empty($he->EVENTNUM))
                    {
                        $alarmed[] = $he->EVENTNUM;
//                            echo "-- Haupteinsatz gefunden ".$he->EVENTNUM.PHP_EOL;
                    }
                }
                // übergeordnet ?
                $ue = $_event->query(['IDMAIN'=>(string)$adata->ID]);
                $uecur = iterator_to_array($ue);
                if (!empty($uecur) && is_array($uecur))
                {
//                        echo "-- ist Haupteinsatz".PHP_EOL;
                    foreach ($uecur as $use)
                    {
//                            echo "-- Untereinsatz gefunden ".$use->EVENTNUM.PHP_EOL;
                        $alarmed[] = $use->EVENTNUM;
                    }
                }
            }
        }
    }

    //print_r($alarmed);

}
*/
