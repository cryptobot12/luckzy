<?php
/*
 *  © CoinSlots 
 *  Demo: http://www.btcircle.com/coinslots
 *  Please do not copy or redistribute.
 *  More licences we sell, more products we develop in the future.  
*/


header('X-Frame-Options: DENY');
error_reporting(0);

$init=true;
include __DIR__.'/../../inc/db-conf.php';
include __DIR__.'/../../inc/wallet_driver.php';
include __DIR__.'/../../inc/db_functions.php';
include __DIR__.'/../../inc/functions.php';

db_query('START TRANSACTION');


if (!logged())exit();
maintenance();

$address = '';

  $player = db_fetch_array(db_query("SELECT * FROM `players` WHERE `hash`='".$_COOKIE['unique_S_']."' LIMIT 1"));

  if($player['state'] != 1){
    echo json_encode(array('error'=>'yes', 'message'=>'Please activate your account before making any transactions.'));
    exit();
  }

  if (!is_numeric($_GET['amount'])) {
    echo json_encode(array('error'=>'yes', 'message'=>'Amount is not a number.'));
    exit();
  }

  $settings = db_fetch_array(db_query("SELECT * FROM `system` WHERE `id`=1 LIMIT 1"));

  if($_GET['amount'] < $settings['min_withdrawal']) {
    echo json_encode(array('error'=>'yes', 'message'=>'You cannot withdraw less than '.$settings['min_withdrawal'].' Coins'));
    exit();
  }

  $currency = 'btc';
  if($_GET['c'] == 'btc') {
    if($_GET['amount'] > $player['btc_balance']*$settings['btc_rate']) {
      echo json_encode(array('error'=>'yes', 'message'=>'You have insufficient funds.'));
      exit();
    }

    $address = prot($_GET['valid_addr']);
    $validate = walletRequest('validateaddress', array($address));
    if (!$validate['isvalid']) {
      echo json_encode(array('error'=>'yes', 'message'=>'Address is not valid.'));
      exit();
    }
    db_query("UPDATE `players` SET `balance`=`balance`-".$_GET['amount'].", `btc_balance`=`btc_balance`-".$_GET['amount']/$settings['btc_rate']." WHERE `id`=$player[id] LIMIT 1");
  }
  else{
    $currency = db_fetch_array(db_query("SELECT `currency`,`rate`, `instructions` FROM `currencies` WHERE `id`=".$_GET['c']." LIMIT 1"));
    if($_GET['amount'] > $player[$currency['currency'].'_balance']*$currency['rate']) {
      echo json_encode(array('error'=>'yes', 'message'=>'You have insufficient funds.'));
      exit();
    }
    db_query("UPDATE `players` SET `balance`=`balance`-".$_GET['amount'].", `".$currency['currency']."_balance`=`".$currency['currency']."_balance`-".$_GET['amount']/$currency['rate']." WHERE `id`=$player[id] LIMIT 1");
  }
    $address = generateHash(30);

    $withdrawned = 0;
    if($_GET['c'] == 'btc') {
      $rate = $settings['btc_rate'];
      if (!$settings['withdrawal_mode']) {
        $withdrawned = 1;
        $txid = walletRequest('sendtoaddress', array($_GET['valid_addr'], $_GET['amount'] / $rate));
        db_query("INSERT INTO `transactions` (`player_id`,`amount`,`txid`) VALUES ($player[id]," . (0 - $_GET['amount'] / $rate) . ",'$txid')");
        echo json_encode(array('error'=>'no', 'txid'=>$txid));
      }
      else echo json_encode(array('error'=>'half'));
    }
    else{
    $rate = $currency['rate'];
    }
    db_query("INSERT INTO `withdrawals` (`player_id`,`amount`,`coins_amount`,`currency`,`address`,`withdrawned`) VALUES ($player[id]," . $amount / $rate . ",".$_GET['amount'].",'".prot($_GET['c'])."','$address', $withdrawned)");

    if($_GET['c'] != 'btc') echo json_encode(array('error'=>'no', 'id'=>$address, 'instructions' => $currency['instructions']));


db_query('COMMIT');

?>
