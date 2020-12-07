<?php
/**
 * Copyright (c) 4mengroup GmbH, Ramsau 160, A-6284 Ramsau im Zillertal
 * Alle Rechte vorbehalten.
 */

/**
 * Created by PhpStorm.
 * User: uhh
 * Date: 27.05.2019
 * Time: 13:41
 */
error_reporting(E_ALL);
include("../inc/funclib.php");
include("../inc/mongo.php");
include("../inc/config.php");

require dirname(__DIR__,1) . '/vendor/autoload.php';

$_mitglieder = new Mongo();
$_mitglieder->setCollection('mitglieder');
echo "Read Inputfile ...".PHP_EOL;
$reader = Asan\PHPExcel\Excel::load('user.xlsx',function(Asan\PHPExcel\Reader\Xlsx $reader) {
   $reader->setSheetIndex(0);

});
$count = count($reader);
$progress = 0;
echo "Found ".count($reader)." rows.".PHP_EOL;
$rows = 0;
foreach ($reader as $row) {
   if ($rows == 0)
   {
       $cols = $row;
       foreach ($cols as $ci=>$cn)
       {
           $cols[$ci] = str_replace(['ä','ö','ü','ß',' '],['ae','oe','ue','ss','-'],strtolower($cn));
           $cols[$ci] = preg_replace('/[^a-z0-9]/','-',$cols[$ci]);
           $cols[$ci] = str_replace('--','-',$cols[$ci]);

       }
   }
   else
   {
       $ins = [];
       $ins['id'] = (int)$row[0];
       $ins['username'] = (string)$row[1];
       $ins['osid'] = (int)$row[2];
       $_mitglieder->update(['id'=>$ins['id']],['$set'=>$ins],['upsert'=>true]);
   }
   ++$rows;
   if ((int)($rows * (100/$count)) != $progress) {
      $progress = (int)($rows * (100/$count));
      echo $progress." %".chr(13);
   }
}
$_mitglieder->createIndex('id',1);
$_mitglieder->createIndex('ortsstelle',1);
$_mitglieder->createIndex('osid',1);
