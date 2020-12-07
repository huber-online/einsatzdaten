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

$OS = [
                ['ID'=>(int)-700474655,'mailserver'=>'mail03.4mengroup.com','mailuser'=>'einsatz-tux@brd.tirol','mailpass'=>'Tristner#16','diveratoken'=>'N_aZ0DZca7J213T1WEQeENjhu8Xy5i8pox5qMK9BRVYE2JeW6HRnWevvSEhO3_DW','diveraversion'=>'alarm']
      ];

foreach ($OS as $o)
{
    // Check Alarm-Email
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
//            echo $hstr.PHP_EOL;
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
                    if ($structcontent->subtype == 'PLAIN' || $structcontent->subtype == 'HTML')
                    {
                        $body = imap_fetchbody($mbox, $msgno, $structid + 1);
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

//        print_r($alarmed);

    }
echo "Daten;";
    $e = $_osevent->query(['OS'=>$o['ID']]);
    $events = iterator_to_array($e);
    foreach ($events as $akt)
    {
        // laufende Einsätze auslesen
        $data = $_event->findOne(['ID'=>(int)$akt->IDEVENT,'STATUS'=>['$eq'=>'finished']]);
        if (!empty($data->ID))
        {
            $diveracall = "";
            $loc = "";
            $txt = $data->NAMEEVENTTYPE;
            if (!empty($data->noasdata->ERGEBNIS_RD)) $txt .= " - ".$data->noasdata->ERGEBNIS_RD;
            if (!empty($data->noasdata->ERGEBNIS_FW)) $txt .= " | ".$data->noasdata->ERGEBNIS_FW;
            $txt .= "\n\n";
            if (!empty($data->eventpos[0]))
            {
                $pos = $data->eventpos[0];
                if (!empty($pos->ZIPCODE)) $txt .= $pos->ZIPCODE.' ';
                if (!empty($pos->CITY)) $txt .= $pos->CITY;
                $txt .= ", ";
                if (!empty($pos->STREET1)) $txt .= $pos->STREET1;
                if (!empty($pos->HOUSENUMBER)) $txt .= ' '.$pos->HOUSENUMBER;
                $txt .= "\n";
                if (!empty($pos->ADDROBJNAME)) $txt .= $pos->ADDROBJNAME.', ';
                if (!empty($pos->INFO_LOCATION)) $txt .= $pos->INFO_LOCATION;
                $txt .= "\n\n";
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

            echo $diveracall.PHP_EOL.PHP_EOL;
        }
    }
}

