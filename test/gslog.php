<?php
/**
 * Created by PhpStorm.
 * User: uhh
 * Date: 06.07.2017
 * Time: 17:01
 */
//if (!defined("__MONGOSERVER__")) define("__MONGOSERVER__","localhost");
//if (!defined("__MONGOPORT__")) define("__MONGOPORT__",27017);
//if (!defined("__MONGODB__")) define("__MONGODB__","bruser");
//
//require_once ("../include/classlib.php");
//
//$_mongo = new MDBConnection();
//
//$_stats = $_mongo->getCollection("statistik");
//$_stats->ensureIndex(["einsatz_von"=>1]);
//$_stats->ensureIndex(["einsatz_bis"=>1]);
//$_stats->ensureIndex(["osid"=>1]);

$out = [];
$a = requestCurl("https://bergrettung.tirol/php/pw.php",["username"=>'huberu',"pwd"=>'IbdsU']);
die($a);
if (preg_match("/Intranet Bergrettung Tirol/",$a))
{

    if ($c = requestCurl("https://bergrettung.tirol/MountainRescue/Member.action?ShowPersonalSettings=action",null))
    {
        die ($c);
        if (preg_match("/member\.localeOfficeId.*selected[^\>]+\>([^\<]+)\</", $c, $d))
        {
            $out["organisation"] = $d[1];
        }
        if (preg_match_all('/member\.lastname".*value.*"([^"]*)"/U', $c, $d))
        {
            $out["nachname"] = $d[1][0];
        }
        if (preg_match_all('/member\.firstname".*value.*"([^"]*)"/U', $c, $d))
        {
            $out["vorname"] = $d[1][0];
        }
        if (preg_match_all('/member\.email".*value.*"([^"]*)"/U', $c, $d))
        {
            $out["email"] = $d[1][0];
        }
        if (preg_match_all('/member\.mobile([^"]+)".*value.*"([^"]*)"/U', $c, $d))
        {
            $tel = [];
            for ($ic = 0; $ic < count($d[1]); $ic++)
            {
                if (!empty($d[2][$ic])) $tel[strtolower($d[1][$ic])] = $d[2][$ic];
            }
            if (!empty($tel["private.countrycode"]) && !empty($tel["private.prefix"]) && !empty($tel["private.number"]))
            {
                $out["tel"] = '+' . $tel["private.countrycode"] . $tel["private.prefix"] . $tel["private.number"];
                echo json_encode($out) . PHP_EOL;
            }
        }
        echo json_encode($out);
        $logoff = requestCurl("https://bergrettung.tirol/MountainRescue/MainApplication.action?Logout=action");
    }
    if (isset($argv[1]) && $argv[1] == "stats")
    {
        for ($os = 1; $os < 95; $os++)
        {
            if ($os != 80 && $os != "79")
            {
                $pdata = ['GenerateStatistic' => 'action', 'statisticSearch.dateFrom_hidden' => date('d.m.Y',strtotime('01.01.'.date('Y'))), 'statisticSearch.dateFrom' => date('d.m.Y',strtotime('01.01.'.date('Y'))),
                    'statisticSearch.dateTo_hidden' => date('d.m.Y'), 'statisticSearch.dateTo' => date('d.m.Y'), 'statisticSearch.districtId' => '', 'statisticSearch.localoffices' => $os];
                if ($c = requestCurl('https://bergrettung.tirol/MountainRescue/OperationStatistic.action', $pdata))
                {
                    if ($pdf = requestCurl('https://bergrettung.tirol/MountainRescue/OperationStatistic.action?PrintStatistic=action'))
                    {
                        file_put_contents($os.'.pdf', $pdf);
                        exec('pdf2json -f 1 -l 1 -compress -i -enc UTF-8 /home/bergrettung/public_html/api/'.$os.'.pdf /home/bergrettung/public_html/api/'.$os.'.json');
                        echo "OS read ".$os.PHP_EOL;
                    }
                }
            }
        }
    }
    if ((isset($argv[2]) && $argv[2] == "collect") || (isset($argv[1]) && $argv[1] == "collect"))
    {
        for ($os = 1; $os < 95; $os++)
        {
            if ($os != 80 && $os != "79")
            {
                if (file_exists($os.".json"))
                {
                    readpdf($os);
                }
            }
        }
    }
}
function requestCurl( $query,$data=null ) {
    $ch = curl_init( $query );
    if ($data != null){
        curl_setopt($ch,CURLOPT_HEADER,true);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_HTTPHEADER,["Content-Type:multipart/form-data"]);
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
    }
    else
    {
        curl_setopt($ch,CURLOPT_POST,0);
    }
    curl_setopt ($ch, CURLOPT_COOKIEJAR, 'cookie.txt'); // cookie.txt
    curl_setopt ($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
    curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    //curl_setopt($ch, CURLOPT_SSLVERSION, 4);
    curl_setopt ($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; U; Linux i586; de; rv:5.0) Gecko/20100101 Firefox/5.0');

    if( !$data = curl_exec( $ch )) {
        echo 'Curl execution error.', curl_error( $ch ) ."\n";
        return false;
    }
    curl_close( $ch );
    return $data;
}

function readpdf($os)
{
    global $_stats;

    $f = file_get_contents($os.".json");
    $d = json_decode($f, true);
    $tx = [];
    foreach ($d[0]['text'] as $t)
    {
        $tx[$t[0]][] = $t;
    }
    ksort($tx);
    $last = null;
    foreach ($tx as $z => $t)
    {
        if ($last !== null && ($z - $last) < 5)
        {
            foreach ($t as $ti)
            {
                $tx[$last][] = $ti;
            }
            unset($tx[$z]);
        }
        $last = $z;
    }
    foreach ($tx as $z => $col)
    {
        $ol = [];
        foreach ($col as $s)
        {
            $ol[$s[1]] = $s;
        }
        ksort($ol);
        $tx[$z] = $ol;
    }
    $tx = array_values($tx);
    foreach ($tx as $z => $t)
    {
        $tx[$z] = array_values($t);
    }

    $ezg = 0;
    $rec = [];
    $rec['osid'] = $os;
    $rec['statdatum'] = date('d.m.Y');
    $rec['statdatumTS'] = (int)time();
    for ($z = 0; $z < count($tx); $z++)
    {
        if ($tx[$z][0][5] == "Einsätze von:")
        {
            echo $tx[$z][0][5] . " " . $tx[$z][1][5] . PHP_EOL;
            $rec['von'] = $tx[$z][1][5];
            $rec['vonTS'] = (int)strtotime($tx[$z][1][5]);
        }
        if ($tx[$z][0][5] == "Einsätze bis:")
        {
            echo $tx[$z][0][5] . " " . $tx[$z][1][5] . PHP_EOL;
            $rec['bis'] = $tx[$z][1][5];
            $rec['bisTS'] = (int)strtotime($tx[$z][1][5]);
        }
        if ($tx[$z][0][5] == "Ortstellen:")
        {
            echo $tx[$z][0][5] . " " . $tx[$z][1][5] . PHP_EOL;
            $rec['ortsstelle'] = str_replace("Bergrettung-","",$tx[$z][1][5]);
        }
        if ($tx[$z][0][5] == "Einsätze Gesamt:")
        {
            $ezg += 1;
            if ($ezg == 2)
            {
                echo $tx[$z][0][5] . " " . $tx[$z][1][5] . " " . $tx[$z][2][5] . " " . $tx[$z][3][5] . PHP_EOL;
                $rec['einsatz_gesamt'] = (int)$tx[$z][1][5];
                $rec['einsatz_eigene'] = (int)$tx[$z][2][5];
                $rec['einsatz_beteiligt'] = (int)$tx[$z][3][5];
            }
        }
        if (preg_match("/Einsatzzeit/", $tx[$z][0][5]))
        {
            echo $tx[$z][0][5] . " " . $tx[$z][5][5] . " " . $tx[$z][7][5] . " " . $tx[$z][8][5] . " " . $tx[$z][10][5] . " " . $tx[$z][11][5] . " " . $tx[$z][13][5] . PHP_EOL;
            $rec['einsatzzeit_gesamt'] = (double)$tx[$z][5][5];
            $rec['einsatzzeit_gesamt_mittel'] = (double)$tx[$z][7][5];
            $rec['einsatzzeit_eigene'] = (double)$tx[$z][8][5];
            $rec['einsatzzeit_eigene_mittel'] = (double)$tx[$z][10][5];
            $rec['einsatzzeit_beteiligt'] = (double)$tx[$z][11][5];
            $rec['einsatzzeit_beteiligt_mittel'] = (double)$tx[$z][13][5];
        }

        if (preg_match("/Beteiligte/", $tx[$z][0][5]))
        {
            echo $tx[$z][0][5] . " " . $tx[$z][5][5] . " " . $tx[$z][7][5] . " " . $tx[$z][8][5] . " " . $tx[$z][10][5] . " " . $tx[$z][11][5] . " " . $tx[$z][13][5] . PHP_EOL;
            $rec['teilnehmer_gesamt'] = (int)$tx[$z][5][5];
            $rec['teilnehmer_gesamt_mittel'] = (double)$tx[$z][7][5];
            $rec['teilnehmer_eigene'] = (int)$tx[$z][8][5];
            $rec['teilnehmer_eigene_mittel'] = (double)$tx[$z][10][5];
            $rec['teilnehmer_beteiligt'] = (int)$tx[$z][11][5];
            $rec['teilnehmer_beteiligt_mittel'] = (double)$tx[$z][13][5];
        }

        if (preg_match("/(Arbeitsunfall|Fehlalarm)/", $tx[$z][0][5]))
        {
            echo $tx[$z][0][5] . " " . $tx[$z][1][5] . " " . $tx[$z][2][5] . " " . $tx[$z][3][5] . " " . $tx[$z][4][5] . " " . $tx[$z][5][5] . PHP_EOL;
            $rec[$tx[$z][0][5]] = (int)$tx[$z][1][5];
            $rec[$tx[$z][2][5]] = (int)$tx[$z][3][5];
            $rec[$tx[$z][4][5]] = (int)$tx[$z][5][5];
        }
        if (preg_match("/Ski/", $tx[$z][0][5]))
        {
            echo $tx[$z][0][5] . " " . $tx[$z][1][5] . " " . $tx[$z][2][5] . " " . $tx[$z][3][5] . " " . $tx[$z][4][5] . " " . $tx[$z][6][5] . PHP_EOL;
            $rec[$tx[$z][0][5]] = (int)$tx[$z][1][5];
            $rec[$tx[$z][2][5]] = (int)$tx[$z][3][5];
            $rec[$tx[$z][4][5]] = (int)$tx[$z][6][5];
        }

        if (preg_match("/Verkehr/", $tx[$z][0][5]))
        {
            echo $tx[$z][0][5] . " " . $tx[$z][1][5] . PHP_EOL;
            $rec[$tx[$z][0][5]] = (int)$tx[$z][1][5];
        }

    }
    $_stats->update(['osid'=>(int)$os,'vonTS'=>$rec['vonTS'],'bisTS'=>$rec['bisTS']],$rec,['upsert'=>1]);
}