<?php
/*
 *  © CoinSlots 
 *  Demo: http://www.btcircle.com/coinslots
 *  Please do not copy or redistribute.
 *  More licences we sell, more products we develop in the future.  
*/

if (!isset($init)) exit();

session_start();

function prot($hodnota, $max_delka = 0)
{
    $text = db_real_escape_string(strip_tags($hodnota));
    if ($max_delka != 0) $vystup = substr($text, 0, $max_delka);
    else  $vystup = $text;
    return $vystup;
}

function generateHash($length, $capt = false)
{
    if ($capt == true) $possibilities = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ';
    else $possibilities = 'abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $return = '';
    for ($i = 0; $i < $length; $i++) $return .= $possibilities[mt_rand(0, strlen($possibilities) - 1)];
    return $return;
}

function random_num($length)
{
    $possibilities = '1234567890';
    $return = '';
    for ($i = 0; $i < $length; $i++) $return .= $possibilities[mt_rand(0, strlen($possibilities) - 1)];
    return $return;
}

function generateSlotsSeed()
{

    $server_seed = array(
        'wheel1' => getInitial(),
        'wheel2' => getInitial(),
        'wheel3' => getInitial(),
        'seed_num' => random_num(8),
        'salt' => generateHash(36)
    );

    return $server_seed;
}

function slotsSeedExport($seed)
{
    if (!empty($seed)) {
        $seed = unserialize($seed);
        $return = 'wheel_1:[' . implode(',', $seed['wheel1']) . '],'
            . 'wheel_2:[' . implode(',', $seed['wheel2']) . '],'
            . 'wheel_3:[' . implode(',', $seed['wheel3']) . '],'
            . 'seed_num:[' . $seed['seed_num'] . '],'
            . 'salt:[' . $seed['salt'] . ']';
        return $return;
    } else return '';
}

function getInitial()
{

    $array = array();

    for ($i = 0, $spaces = 0; $i < 74; $i++) {

        if ($i < 2) $new = 1;
        else if ($i < 7) $new = 2;
        else if ($i < 16) $new = 3;
        else if ($i < 26) $new = 4;
        else if ($i < 41) $new = 5;
        else if ($i < 74) $new = 6;

        $array[] = $new;

        if ($spaces < 54) {
            $array[] = 0;
            $spaces++;
        }


    }

    shuffle($array);

    $newArray = array();

    for ($i = 0; $i < 128; $i++) {

        $index = rand(0, (127 - $i));
        $newArray[] = $array[$index];

        array_splice($array, $index, 1);

    }

    shuffle($newArray);

    return $newArray;

}

function newPlayer()
{
    do $hash = generateHash(32);
    while (db_num_rows(db_query("SELECT `id` FROM `players` WHERE `hash`='$hash' LIMIT 1")) != 0);

    $client_seed = random_num(8);
    db_query("INSERT INTO `players` (`hash`,`slots_seed`,`dice_seed`,`initial_shuffle`, `client_seed`) VALUES ('$hash','" . serialize(generateSlotsSeed()) . "','" . random_num(8) . "','" . generateInitialShuffle($client_seed) . "', '" . $client_seed . "')");

    setcookie('unique_S_', $hash, (time() + 60 * 60 * 24), '/');
    setcookie('unique_S_', $hash, (time() + 60 * 60 * 24), '/');
}

function zkrat($str, $max, $iflonger)
{
    if (strlen($str) > $max) {
        $str = substr($str, 0, $max) . $iflonger;
    }
    return $str;
}

function n_num($num, $showall = false)
{
    $r = sprintf("%.8f", $num);
    if ($showall == true) return $r;
    else return rtrim(rtrim($r, '0'), '.');
}

function logged()
{
    if (isset($_SESSION['logged']) && $_SESSION['logged'] == true) return true;
    else return false;
}

function game()
{
    return $_COOKIE['game'];
}

function bbcode($str)
{

    $str = str_replace(array(
        '[B]', '[/B]', '[b]', '[/b]', '[i]', '[/i]', '[I]', '[/I]', '[U]', '[/U]', '[u]', '[/u]', '[br]', '[BR]'
    ), array(
        '<b>', '</b>', '<b>', '</b>', '<i>', '</i>', '<i>', '</i>', '<u>', '</u>', '<u>', '</u>', '<br>', '<br>'
    ), $str);

    return $str;
}

function getSpin($multip)
{

    switch ($multip) {

        case 0:
            return '-';
        case 1:
            return '[6]';
        case 2:
            return '[6] [6]';
        case 5:
            return '[6] [6] [6]';
        case 10:
            return '[5] [5] [5]';
        case 50:
            return '[4] [4] [4]';
        case 200:
            return '[3] [3] [3]';
        case 600:
            return '[2] [2] [2]';
        default:
            return '[1] [1] [1]';

    }

}

function profit($profit)
{
    if ($profit < 0) {
        $class = 'loss';
    } else if ($profit > 0) $class = 'win';
    else $class = 'neutral';

    return '<span class="profit-' . $class . '"><span class="st-plus"></span>' . $profit . ' Coins</span>';

}

function house_edge()
{
    $settings = db_fetch_array(db_query("SELECT * FROM `system` WHERE `id`=1"));
    $p_return = 3.57627869
        + 6.95228577
        + 2.38418579
        + 1.60932541
        + 8.56804848
        + 29.59871292
        + 42.60420799;
    $p_return += 0.00000381 * $settings['jackpot'] * 100;
    return 100 - $p_return;

}

function maintenance()
{

    $settings = db_fetch_array(db_query("SELECT `maintenance` FROM `system` LIMIT 1"));

    if ($settings['maintenance']) exit();

}

/*________________________JACK_____________________________*/
function card_value($card_val)
{
    if ($card_val == 'A') return 1;
    else if ($card_val == 'J' || $card_val == 'Q' || $card_val == 'K')
        return 10;
    else return $card_val;
}

function dealerPlays($dealer_deck, $final_shuffle, $used_cards)
{
    $settings = db_fetch_array(db_query("SELECT * FROM `system` WHERE `id`=1 LIMIT 1"));

    $threshold = 17; // under = HIT


    while (max(getSums($dealer_deck)) < $threshold) {
        $dealer_deck[] = $final_shuffle[$used_cards];
        $used_cards++;
    }
    if ($settings['hits_on_soft'] == 1 && max(getSums($dealer_deck)) == $threshold && count(getSums($dealer_deck)) == 2) {
        $dealer_deck[] = $final_shuffle[$used_cards];
        $used_cards++;

        while (min(getSums($dealer_deck)) < $threshold) {
            $dealer_deck[] = $final_shuffle[$used_cards];
            $used_cards++;
        }
    }

    return $dealer_deck;
}

function getSums($deck)
{
    $sum = 0;
    $card_vals = array();
    foreach ($deck as $cardStr) {
        $card = explode('_', $cardStr);
        $val = card_value($card[1]);

        $sum += $val;
        $card_vals[] = $val;
    }
    $sums = array($sum);
    if (in_array(1, $card_vals) && ($sum + 10) <= 21) $sums[] = ($sum + 10);

    return $sums;
}

function stringify_shuffle($shuffle)
{
    if(!empty($shuffle)) {
        $cards = unserialize($shuffle);
        return implode(';', $cards['initial_array']) . ';random-string-' . $cards['random_string'];
    }
}

function playerWon($player_id, $game_id, $wager, $d_deck, $regular_or_tie, $blackjack, $final_shuffle = '')
{
    $settings = db_fetch_array(db_query("SELECT * FROM `system` WHERE `id`=1 LIMIT 1"));

    $gameData = db_fetch_array(db_query("SELECT * FROM `games` WHERE `id`=$game_id LIMIT 1"));
    /*$mysqlerr=db_error();*/
    $player = db_fetch_array(db_query("SELECT `client_seed` FROM `users`  WHERE `id`=$player_id LIMIT 1"));
    $wager = $gameData['bet_amount'];

    $first_won_second_lose = false;

    if ($gameData['player_deck_2'] != '') {
        $first_won = true;
        $second_won = true;


        $dealer_max = max(getSums($d_deck));
        $player_max = max(getSums(unserialize($gameData['player_deck'])));
        $player_max_2 = max(getSums(unserialize($gameData['player_deck_2'])));

        if ($player_max > 21 || ($player_max <= $dealer_max && $dealer_max <= 21)) $first_won = false;
        if ($player_max_2 > 21 || ($player_max_2 <= $dealer_max && $dealer_max <= 21)) $second_won = false;

        if ($first_won != $second_won) $first_won_second_lose = true;

        //D/mysql_query("UPDATE `games` SET `note`='".$first_won.":".$second_won.":".$first_won_second_lose."|D-$dealer_max:P-$player_max:P2-$player_max_2 || ".$mysqlerr."' WHERE `id`=$gameData[id] LIMIT 1");
    }

    if ($settings['bj_pays'] == 0) $bj_pays = 2.5;
    else $bj_pays = 2.2;

    if ($blackjack == true) $regularWin = $bj_pays;
    else $regularWin = 2;
    if ($regular_or_tie == 'tie') $multip = ($settings['tie_dealerwon'] == 1) ? 0 : 1;
    else if ($regular_or_tie == 'lose') $multip = ($first_won_second_lose) ? 1 : 0;
    else if ($regular_or_tie == 'regular') $multip = ($first_won_second_lose) ? 1 : $regularWin;
    if ($first_won_second_lose) $multip = 1;
    if ($final_shuffle != '') {
        $endGame = ",`last_client_seed`=`client_seed`"
            . ",`last_final_shuffle`='$final_shuffle'"
            . ",`last_initial_shuffle`=`initial_shuffle`"
            . ",`initial_shuffle`='" . generateInitialShuffle($player['client_seed']) . "'";

        db_query("UPDATE `games` SET `multiplier`=$multip WHERE `id`=$game_id LIMIT 1");
    } else $endGame = "";

    if ($regular_or_tie == 'tie') {
        if ($settings['tie_dealerwon'] != 1) $multip = 1;
    }
    db_query("UPDATE `players` SET `balance`=`balance`+" . ($wager * $multip) . " $endGame WHERE `id`=$player_id LIMIT 1");

}

function generateInitialShuffle($client_seed)
{
    $settings = db_fetch_array(db_query("SELECT `number_of_decks` FROM `system` LIMIT 1"));
    $initial_shuffle = array();
    for ($i = 0; $i < $settings['number_of_decks']; $i++) {
        shuffle($initial_shuffle);
        $newDeck = listDeck();
        shuffle($newDeck);
        $initial_shuffle = array_merge($initial_shuffle, listDeck());
        shuffle($initial_shuffle);
    }
    shuffle($initial_shuffle);
    $initial_shuffle = cs_shuffle($client_seed, $initial_shuffle);
    return serialize(array('initial_array' => $initial_shuffle, 'random_string' => generateHash(32)));
}

function listDeck()
{
    $blacks = array(
        '♠_A_black', '♥_A_red', '♦_A_red', '♣_A_black',
        '♠_2_black', '♥_2_red', '♦_2_red', '♣_2_black',
        '♠_3_black', '♥_3_red', '♦_3_red', '♣_3_black',
        '♠_4_black', '♥_4_red', '♦_4_red', '♣_4_black',
        '♠_5_black', '♥_5_red', '♦_5_red', '♣_5_black',
        '♠_6_black', '♥_6_red', '♦_6_red', '♣_6_black',
        '♠_7_black', '♥_7_red', '♦_7_red', '♣_7_black',
        '♠_8_black', '♥_8_red', '♦_8_red', '♣_8_black',
        '♠_9_black', '♥_9_red', '♦_9_red', '♣_9_black',
        '♠_10_black', '♥_10_red', '♦_10_red', '♣_10_black',
        '♠_J_black', '♥_J_red', '♦_J_red', '♣_J_black',
        '♠_Q_black', '♥_Q_red', '♦_Q_red', '♣_Q_black',
        '♠_K_black', '♥_K_red', '♦_K_red', '♣_K_black',
    );

    $return = array();

    foreach ($blacks as $black) {
        $return[] = $black;
    }
    return $return;
}

function cs_shuffle($client_seed, $deck)
{

    $final_deck = $deck; // copy deck to final_deck

    srand((int)$client_seed);

    foreach ($final_deck as $key => $final_card) {
        do {
            $deck_index = rand(0, count($deck) - 1);
        } while ($deck[$deck_index] === null);

        $final_deck[$key] = $deck[$deck_index];

        $deck[$deck_index] = null;
    }

    srand(mt_rand());  //D/$final_deck[]='♠_8_black';$final_deck[]='♠_8_black';$final_deck[]='♠_J_black';$final_deck[]='♠_2_black';$final_deck[]='♠_3_black';$final_deck[]='♠_A_black';$final_deck[]='♠_4_black';$final_deck=array_reverse($final_deck);

    return $final_deck;
}




function get_count($player = '', $filter = '')
{
    $where = '';
    if($player != '') $where .= "WHERE `player`='$player'";
    else if($player == '' && $filter != '') $where .= "WHERE ";

    if($filter == 'wins') $where .= "`multiplier` > 1";
    else if($filter == 'losses') $where .= "`multiplier` < 1";
    else if($filter == 'ties') $where.= "`multiplier` = 1";

    $pocet = 0;
    if (isset($_GET['g']) && $_GET['g'] == 'blackjack') {
        $pocet += db_num_rows(db_query("SELECT `id` FROM `games` $where"));
    }
    else if (isset($_GET['g'])) {
        if($where != '') $where .= " AND `game`='" . $_GET['g'] . "'";
        else $where = "WHERE `game`='" . $_GET['g'] . "'";
        $pocet += db_num_rows(db_query("SELECT `id` FROM `spins` $where"));
    }
    else {
        $pocet += db_num_rows(db_query("SELECT `id` FROM `spins` $where"));
        $pocet += db_num_rows(db_query("SELECT `id` FROM `games` $where"));
    }
    return $pocet;
}
function get_wagered($player = ''){
    $where = '';
    if($player != '') $where = "WHERE `player`='$player'";
    $soucet = 0;
    if (isset($_GET['g']) && $_GET['g'] == 'blackjack') {
        $q = db_query("SELECT `bet_amount` FROM `games` $where");
        while($row = db_fetch_array($q)){
            $soucet += $row['bet_amount'];
        }
    }
    else if (isset($_GET['g'])) {
        if($where != '') $where .= " AND `game`='" . $_GET['g'] . "'";
        else $where = "WHERE `game`='" . $_GET['g'] . "'";
        $q = db_query("SELECT `bet_amount` FROM `spins` $where");
        while($row = db_fetch_array($q)){
            $soucet += $row['bet_amount'];
        }
    }
    else {
        $q = db_query("SELECT `bet_amount` FROM `spins` $where");
        while($row = db_fetch_array($q)){
            $soucet += $row['bet_amount'];
        }
        $q = db_query("SELECT `bet_amount` FROM `games` $where");
        while($row = db_fetch_array($q)){
            $soucet += $row['bet_amount'];
        }
    }
    return $soucet;
}

function real_edge($period = ''){
    $where = "";
    $from = "FROM `spins`";
    if($period != '') $where .= "WHERE `time`>NOW()-INTERVAL ".$period;
    if(isset($_GET['g'])) {
        if($where == '') $where .= "WHERE ";
        else $where .= " AND ";
        if($_GET['g'] == 'blackjack'){
            $from = "FROM `games`";
            $where .= "`ended`=1 AND `winner`!='tie'";
        }
        else $where .= "`game`='" . $_GET['g'] . "'";
        $this_q=db_fetch_array(db_query("SELECT SUM(-1*((`bet_amount`*`multiplier`)-`bet_amount`)) AS `total_profit`,SUM(`bet_amount`) AS `total_wager` $from $where"));
    }
    else{
        $this_q=db_fetch_array(db_query("SELECT SUM(-1*((`bet_amount`*`multiplier`)-`bet_amount`)) AS `total_profit`,SUM(`bet_amount`) AS `total_wager` $from $where"));
        $from = "FROM `games`";
        array_merge($this_q, db_fetch_array(db_query("SELECT SUM(-1*((`bet_amount`*`multiplier`)-`bet_amount`)) AS `total_profit`,SUM(`bet_amount`) AS `total_wager` $from $where")));
    }
    $h_e_['h_e']=($this_q['total_wager']!=0)?(($this_q['total_profit']/$this_q['total_wager'])*100):0; echo ($h_e_['h_e']>=0)?'<td><span style="color: green;">+'.sprintf("%.5f",$h_e_['h_e']).'%</span></td>':'<td><span style="color: #d10000;">'.sprintf("%.5f",$h_e_['h_e']).'%</span></td>';
    echo ($this_q['total_profit']>=0)?'<td><span style="color: green;">+'.sprintf("%.8f",$this_q['total_profit']).'</span></td>':'<td><span style="color: #d10000;">'.sprintf("%.8f",$this_q['total_profit']).'</span></td>';
}

function last_won($interval){
    $soucet = 0;
    $q = db_query("SELECT `payout` FROM `spins` WHERE `time` > NOW() - INTERVAL $interval");
    while($row = db_fetch_array($q)){
        $soucet += $row['payout'];
    }
    $q = db_query("SELECT `bet_amount`, `multiplier` FROM `games` WHERE `time` > NOW() - INTERVAL $interval");
    while($row = db_fetch_array($q)){
        $soucet += $row['bet_amount']*$row['multiplier'];
    }
    return $soucet;
}

function biggest_win($interval = ''){
    $where = '';
    if(!empty($interval)) $where = "WHERE `time` > NOW() - INTERVAL ".$interval;
    $biggest = db_fetch_array(db_query("SELECT `payout` FROM `spins` $where ORDER BY `payout` DESC LIMIT 1"));
    $biggest_game = db_fetch_array(db_query("SELECT `bet_amount`*`multiplier` AS `payout` FROM `games` $where ORDER BY `payout` DESC LIMIT 1"));
    $biggest_game['payout'] > $biggest['payout'] ? $biggest = $biggest_game['payout']: $biggest = $biggest['payout'];
    return $biggest;
}

function get_deposits()
{
    $deposits = '';
    $query = db_query("SELECT * FROM `deposits` WHERE `confirmed`=1");
    while ($row = db_fetch_array($query)) {
        if ($row['currency'] == 'btc') {
            $name = 'Bitcoin';
        } else {
            $currency = db_fetch_array(db_query("SELECT `currency` FROM `currencies` WHERE `id`='" . $row['currency'] . "' LIMIT 1"));
            $name = $currency['currency'];
        }
        $deposits .= '<tr><td>' . $name . '</td><td>' . $row['amount'] . '</td><td>' . $row['coins_amount'] . '</td><td>' . $row['address'] . '</td></tr>';
    }
    return $deposits;
}
function get_withdrawals()
{
    $withdrawals = '';
    $query = db_query("SELECT * FROM `withdrawals` WHERE `withdrawned`=1");
    while ($row = db_fetch_array($query)) {
        if ($row['currency'] == 'btc') {
            $name = 'Bitcoin';
        } else {
            $currency = db_fetch_array(db_query("SELECT `currency` FROM `currencies` WHERE `id`='" . $row['currency'] . "' LIMIT 1"));
            $name = $currency['currency'];
        }
        $withdrawals .= '<tr><td>' . $name . '</td><td>' . $row['amount'] . '</td><td>' . $row['coins_amount'] . '</td><td>' . $row['address'] . '</td></tr>';
    }
    return $withdrawals;
}

function get_friends($type){
    $friends = '';
    if ($type === -1){
        $query = db_query("SELECT * FROM `player_relations` WHERE (`player`=".$_SESSION['user_id']." OR `friend`=".$_SESSION['user_id'].") AND `relation`=0");
        if($query != false){
            while ($row = db_fetch_array($query)) {
                $friend = db_fetch_array(db_query("SELECT * FROM `players` WHERE `id`=" . $row['friend'] . " LIMIT 1"));
                if($friend != false) $friends .= '<div>'.$friend['username'].'<a href="javascript:remove_friend('.$row['friend'].')"><span class="glyphicon glyphicon-trash"></span></a></div>';
            }
            if(!empty($friends)) return $friends;
            else return 'No ignored friends';
        }
    }
    elseif($type === 10){
        $query = db_query("SELECT * FROM `player_relations` WHERE `friend`=".$_SESSION['user_id']." AND `relation`=1 AND `state`=0");
        if($query != false){
            while ($row = db_fetch_array($query)) {
                $friend = db_fetch_array(db_query("SELECT * FROM `players` WHERE `id`=" . $row['friend'] . " LIMIT 1"));
                if($friend != false) {
                    $friends .= '<div>'.$friend['username'].'<a href="javascript:approve_friend('.$row['friend'].')"><span class="glyphicon glyphicon-ok"></span></a><a href="javascript:ignore_friend('.$row['friend'].')"><span class="glyphicon glyphicon-remove"></span></a></div>';
                }
            }
        }
        if(!empty($friends)) return $friends;
        else return 'No friend requests';
    }
    else{
        $query = db_query("SELECT * FROM `player_relations` WHERE (`player`=".$_SESSION['user_id']." OR `friend`=".$_SESSION['user_id'].") AND `relation`=1");
        if($query != false){
            while ($row = db_fetch_array($query)) {

                if($row['friend'] == $_SESSION['user_id']) $id = $row['player'];
                else $id = $row['friend'];

                if ($type === 1) $where = "AND `time_last_active` > NOW()-INTERVAL 10 MINUTE AND `chat_status`=1";
                elseif($type === 0) $where = "AND `time_last_active` < NOW()-INTERVAL 10 MINUTE OR `chat_status`=0";

                $friend = db_fetch_array(db_query("SELECT * FROM `players` WHERE `id`=" . $id . " $where LIMIT 1"));
                if($friend != false) {
                    $friends .= '<div>';
                    $friends .= $friend['username'];
                    if(!$row['state']) $friends .= ' <small>(Request sent)</small>';
                    if ($type === 1) $friends .= '<a href="javascript:select_room('.$id.',1)"><span class="glyphicon glyphicon-envelope"></span></a>';
                    $friends .= '<a href="javascript:ignore_friend('.$id.')"><span class="glyphicon glyphicon-remove"></span></a><a href="javascript:remove_friend('.$id.')"><span class="glyphicon glyphicon-trash"></span></a>';
                    $friends .= '</div>';
                }
            }
            if(!empty($friends)) return $friends;
            else{
                if($type === 1) return 'No online friends';
                elseif($type === 0) return 'No offline friends';
            }
        }
    }
}

function count_friends($type = null){
    if ($type === -1){
        $query = db_query("SELECT `player` FROM `player_relations` WHERE (`player`=".$_SESSION['user_id']." OR `friend`=".$_SESSION['user_id'].") AND `relation`=0");
        if($query != false){
            $count = db_num_rows($query);
            return $count;
        }
        else return 0;
    }
    elseif($type === 10){
        $query = db_query("SELECT * FROM `player_relations` WHERE `friend`=".$_SESSION['user_id']." AND `relation`=1 AND `state`=0");
        if($query != false){
            $count = db_num_rows($query);
            return $count;
        }
        else return 0;
    }
    else{
        if ($type === 1){
            $where = "AND `time_last_active` > NOW() - INTERVAL 10 MINUTE AND `chat_status`=1";
        }
        elseif($type === 0){
            $where = "AND `time_last_active` < NOW() - INTERVAL 10 MINUTE OR `chat_status`=0";
        }
        else{
            $where = "";
        }
        $query = db_query("SELECT * FROM `player_relations` WHERE (`player`=".$_SESSION['user_id']." OR `friend`=".$_SESSION['user_id'].") AND `relation`=1");
        $count = 0;
        if($query != false){
            while ($row = db_fetch_array($query)) {
                if($row['friend'] != $_SESSION['user_id']) {
                    $count += db_num_rows(db_query("SELECT * FROM `players` WHERE `id`=" . $row['friend'] . " $where LIMIT 1"));
                }
            }
            return $count;
        }
        else return 0;
    }
}
function get_pms(){
    $pms = '';
    $query = db_query("SELECT * FROM `player_relations` WHERE (`player`=".$_SESSION['user_id']." OR `friend`=".$_SESSION['user_id'].") AND `relation`=1");
    if ($query != false) {
        while ($row = db_fetch_array($query)) {
            if($row['friend'] == $_SESSION['user_id']){
                $id = $row['player'];
                $for = $row['friend'];
            }
            else {
                $id = $row['friend'];
                $for = $row['player'];
            }

            $message = db_num_rows(db_query("SELECT `id` FROM `chat` WHERE (`sender`=" . $for . " AND `for`=" . $id . " OR `sender`=" . $id . " AND `for`=" . $for.") LIMIT 1"));

            if ($message != 0) {
                $friend = db_fetch_array(db_query("SELECT * FROM `players` WHERE `id`=" . $id . " LIMIT 1"));
                $nondisplayed = $message = db_num_rows(db_query("SELECT `id` FROM `chat` WHERE `sender`=" . $id . " AND `displayed`=0 AND `for`=" . $for));
                $pms .= '<a href="javascript:select_room(' . $id . ', 1)">' . $friend['username'] . ' ('.$nondisplayed.')</a>';
            }
        }
        if(!empty($pms)) return $pms;
        else return 'No private messagess';
    }
}

function online_count(){
    return db_num_rows(db_query("SELECT `id` FROM `players` WHERE `time_last_active` > NOW() - INTERVAL 10 MINUTE AND `chat_status`=1 AND `password`!=''"));
}