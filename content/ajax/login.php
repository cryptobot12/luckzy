<?php
/*
 *  © CoinSlots 
 *  Demo: http://www.btcircle.com/coinslots
 *  Please do not copy or redistribute.
 *  More licences we sell, more products we develop in the future.  
*/


header('X-Frame-Options: DENY');

$init=true;
include '../../inc/db-conf.php';
include '../../inc/db_functions.php';
include '../../inc/functions.php';

if (isset($_GET['logout'])) {
  $_SESSION['logged_']=false;
  echo json_encode(array('error' => 'no'));
  setcookie('unique_S_', '', time() - 3600, '/');
  exit();
}

if (!empty($_POST['username']) && !empty($_POST['passwd'])) {
    if (db_num_rows(db_query("SELECT `id` FROM `players` WHERE `username`='".prot($_POST['username'])."' AND `password`='".hash('sha256',$_POST['passwd'])."' LIMIT 1"))!=0) {
      $user = db_fetch_array(db_query("SELECT * FROM `players` WHERE `username`='" . prot($_POST['username']) . "' AND `password`='" . hash('sha256', $_POST['passwd']) . "' LIMIT 1"));
      if (!empty($_POST['totp'])) {
        include '../../inc/ga_class.php';
        $verify = Google2FA::verify_key($user['ga_token'], $_POST['totp'], 0);
        if ($verify == true) {
          $_SESSION['logged_'] = true;
          $_SESSION['user_id'] = $user['id'];
          echo json_encode(array('error' => 'no'));
          exit();
        }
      }
      if ($user['ga_token'] == '') {
        $_SESSION['logged_'] = true;
        $_SESSION['user_id'] = $user['id'];
        echo json_encode(array('error' => 'no'));
        exit();
      }
      echo json_encode(array('error' => 'no', '2f_1' => 'yes'));
      exit();
    }
    else echo json_encode(array('error' => 'yes', 'message' => 'Some of the field are empty'));
}
else echo json_encode(array('error' => 'yes', 'message' => 'Some of the field are empty'));
?>