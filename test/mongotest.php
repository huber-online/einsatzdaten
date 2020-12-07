<?php
/**
 * Copyright (c) 4mengroup GmbH, Ramsau 160, A-6284 Ramsau im Zillertal
 * Alle Rechte vorbehalten.
 */
include("../inc/mongo.php");
include("../inc/funclib.php");
include("../inc/config.php");

$_mongo->setCollection('testmich');
$q = $_mongo->query(array());
$r = iterator_to_array($q);
print_r($r);