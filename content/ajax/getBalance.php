<?php
/*
 *  © CoinSlots 
 *  Demo: http://www.btcircle.com/coinslots
 *  Please do not copy or redistribute.
 *  More licences we sell, more products we develop in the future.  
*/


error_reporting(0);
header('X-Frame-Options: DENY'); 

$init=true;
include __DIR__.'/../../inc/db-conf.php';
include __DIR__.'/../../inc/db_functions.php';
include __DIR__.'/../../inc/functions.php';

if (!logged())exit();

$player=db_fetch_array(db_query("SELECT `balance`,`id` FROM `players` WHERE `hash`='".prot($_GET['_unique'])."' LIMIT 1"));

maintenance();


echo json_encode(array('balance'=>$player['balance']));

