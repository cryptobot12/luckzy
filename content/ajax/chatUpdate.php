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

maintenance();
if (empty($_GET['_unique']) || db_num_rows(db_query("SELECT `id` FROM `players` WHERE `hash`='".$_COOKIE['unique_S_']."' LIMIT 1"))==0) exit();

$player=db_fetch_array(db_query("SELECT `id` FROM `players` WHERE `hash`='".$_COOKIE['unique_S_']."' LIMIT 1"));

if (empty($_GET['lastId']) || (int)$_GET['lastId']==0) {
  $lastid=0;
  $limit=100;
}
else {
  $lastid=(int)$_GET['lastId'];
  $limit=500;
}

$content='';
if($_COOKIE['pm']) {
  $where = "AND (`sender`=" . $player['id'] . " AND `for`=" . $_COOKIE['chat_room'] . " OR `sender`=" . $_COOKIE['chat_room'] . " AND `for`=" . $player['id'].")";
  db_query("UPDATE `chat` SET `displayed`=1 WHERE `for`=".$player['id']." AND `sender`=".$_COOKIE['chat_room']);
}
else $where = "AND `for` IS NULL AND `room`=".$_COOKIE['chat_room'];

$messages=db_query("SELECT * FROM `chat` WHERE (`id`>$lastid) $where ORDER BY `time` DESC,`id` DESC LIMIT $limit");
$messages_array=array();

while ($message=db_fetch_array($messages)) {
  $messages_array[]=$message;  
}

$messages=array_reverse($messages_array);

foreach ($messages as $message) {
  $content.='<div class="chat-message" data-messid="'.$message['id'].'">';  
  $sender=db_fetch_array(db_query("SELECT `username` FROM `players` WHERE `id`=$message[sender] LIMIT 1"));
  
  if ($sender==false) $sender['username']='[unknown]';
  
  $content.='<div class="chat-m-user">'.$sender['username'].'</div>';
  $content.='<div class="chat-m-time">'.date('H:i', strtotime($message['time'])).'</div>';
  $content.='<div class="chat-m-text">'.$message['content'].'</div>';
  $content.='</div>';
}

echo json_encode(array('content'=>$content));

?>
