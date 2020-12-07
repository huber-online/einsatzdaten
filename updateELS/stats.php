<?php
/**
 * Copyright (c) 4mengroup GmbH, Ramsau 160, A-6284 Ramsau im Zillertal
 * Alle Rechte vorbehalten.
 */
error_reporting(E_ALL);
include("../inc/funclib.php");
include("../inc/mongo.php");
include("../inc/config.php");

echo "Starte Datensammlung ...\n";

$o = $_os->count([]);
echo "Ortsstellen: ".$o.PHP_EOL;

$e = $_event->count(['IDMAIN'=>0]);
echo "Einsätze: ".$e.PHP_EOL;

