<?php
/**
 * Copyright (c) 4mengroup GmbH, Ramsau 160, A-6284 Ramsau im Zillertal
 * Alle Rechte vorbehalten.
 */

/**
 * Created by PhpStorm.
 * User: uhh
 * Date: 27.05.2019
 * Time: 13:26
 */

$out = [];
$a = requestCurl("https://bergrettung.tirol/php/pw.php",["username"=>'h.ulli',"pwd"=>'Rosshag65']);

if (preg_match("/Intranet Bergrettung Tirol/",$a))
{
    //echo "auth";
    
	$pdata = ['Export' => 'action'];

        if ($c = requestCurl('https://bergrettung.tirol/MountainRescue/Member.action?ListInit=action')) {
            if ($xls = requestCurl('https://bergrettung.tirol/MountainRescue/Member.action?Export=action')) {
                file_put_contents('mitglieder.xlsx',$xls);
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
    //curl_setopt($ch, CURLOPT_SSLVERSION, 2);
    curl_setopt ($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; U; Linux i586; de; rv:5.0) Gecko/20100101 Firefox/5.0');

    if( !$data = curl_exec( $ch )) {
        echo 'Curl execution error.', curl_error( $ch ) ."\n";
        return false;
    }
    curl_close( $ch );
    return $data;
}
