<?php

$lines_on_page = 100;

set_include_path(get_include_path() . PATH_SEPARATOR . '../libs/globaltree/');
include('helpers.php');
include('playhelp.php');

initAuth();

header("Content-Type: text/html; charset=utf-8");
query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

// аутентификация
authHeader('../../frontend/index.php');

// дизайн
echo '<link rel="stylesheet" href="css/tech.css" type="text/css">';

secureGetRequestData('game', 'param_user', 'type', 'action', 'id', 'offset');
$user = $_SESSION['user_id'];

if (!isAdmin($user) && CFG_ALLOW_LOG_FOR_USERS == 0)
{
    die('error');
}

// Удаление из лога
if ($action == 'del')
{
    // Только для адмниов.
    if (!isAdmin($user)) { exit; }

    $qr = query_result("DELETE FROM `game_log` WHERE `id` = '$id';");

    echo "<script language=javascript>history.go(-1);</script>";

    exit;
}

if ($action == 'del_up')
{
    // Только для адмниов.
    if (!isAdmin($user)) { exit; }

    if ($game != '') { $game_filter = " AND `game` = '$game' "; }
    if ($param_user != '') { $user_filter = " AND `user` = '$param_user' "; }
    if ($type != '') { $type_filter = " AND `type` = '$type' "; }

    $qr = query_result("DELETE FROM `game_log` WHERE `id` >= '$id' $game_filter $user_filter $type_filter ");

    echo "<script language=javascript>history.go(-1);</script>";

    exit;
}

auto_refresh();

// ---------- Определим список игр, доступных для просмотра текущему пользователю

$user_filter = '';
if (!isAdmin($user)) { $user_filter = "AND `owner` = '$user'"; }

$available_for_selection_games = array();
$own_games = array();
$finished_games = array();

// Можно смотреть все игры, если ты админ, или те игры, которые ты сам создал
$qr = query_result("SELECT `id` FROM `tree` WHERE `class` = '".OBJECT_TYPE_GAME."' $user_filter");
while ($row = mysql_fetch_array($qr))
{
    $available_for_selection_games[] = $row['id'];
    $own_games[] = $row['id'];
}

// Также смотреть все игры, которые закончились
$qr = query_result("SELECT `id` FROM `object_data_".OBJECT_TYPE_GAME."` WHERE `finished` = '1'");
while ($row = mysql_fetch_array($qr))
{
    $available_for_selection_games[] = $row['id'];
    $finished_games[] = $row['id'];
}

// А также те игры, в которые ты играл(ешь). Т.е., те, на которые есть принятая заявка.
// Правда, в этом случае, если ты не админ, ты увидишь лог только своих действий.
$qr = query_result("SELECT `game` FROM `game_request` WHERE `user` = '$user' AND `approved` = '1'");
while ($row = mysql_fetch_array($qr))
{
    $available_for_selection_games[] = $row['game'];
}

// Уберем повторы из списка доступных игры.
$available_for_selection_games = array_unique($available_for_selection_games);

if (!in_array($game, $available_for_selection_games) && $game != '') { die('У вас нет доступа к логам этой игры'); }

$available_for_selection_games_list = implode(',', $available_for_selection_games);

$available_for_full_view_games = array_merge($own_games, $finished_games);
$available_for_full_view_games_list = implode(',', $available_for_full_view_games);

// ---------- Выведем форму фильтрации лога

echo "<form method=get>";

echo "<select name=game>";
if ($game == '') { $sel = 'selected '; } else { $sel = ''; }
echo "<option value=\"\" $sel>Все игры";
foreach ($available_for_selection_games as $current_game)
{
    if ($game == $current_game) { $sel = ' selected '; } else { $sel = ''; }
    $current_game_name = getName($current_game);
    echo "<option value=$current_game $sel>$current_game_name";
}
echo "</select>";

echo "<select name=param_user>";
if ($param_user == '') { $sel = 'selected '; } else { $sel = ''; }
if (isAdmin($user)) { echo "<option value=\"\" $sel>Все пользователи"; }

$qr = query_result("SELECT `id`, `name` FROM `user`;");
while ($row = mysql_fetch_array($qr))
{
    if (isAdmin($user) || $user == $row['id'])
    {
        if ($param_user == $row['id']) { $sel = ' selected '; } else { $sel = ''; }
        echo "<option value={$row['id']} $sel>{$row['name']}";
    }
}
echo "</select>";

echo "<select name=type>";
if ($type == '') { $sel = 'selected '; } else { $sel = ''; }
echo "<option value=\"\" $sel>Все события";
$available_events = get_available_events();
foreach ($available_events as $type_name=>$type_id)
{
    if ($type == $type_id) { $sel = ' selected '; } else { $sel = ''; }
    echo "<option value=$type_id $sel>$type_name";
}
echo "</select>";

echo "<input type=submit value=Вывести>";
echo "</form>";

// ---------- Выведем лог

$game_filter = ''; $user_filter = ''; $type_filter = '';
if ($game != '') { $game_filter = " AND `game` = '$game' "; }
if ($param_user != '') { $user_filter = " AND `user` = '$param_user' "; }
if ($type != '') { $type_filter = " AND `type` = '$type' "; }

if ($offset == '') { $offset = 0; }

if (!isAdmin($user))
{
    if (count($available_for_selection_games) == 0)
    {
        $access_check = " AND (`user` = '$user')";
    } else {
        if (count($available_for_full_view_games) == 0)
        {
            $access_check = " AND (`game` IN ($available_for_selection_games_list) AND `user` = '$user')";
        } else {
            $access_check = " AND (`game` IN ($available_for_full_view_games_list) OR (`game` IN ($available_for_selection_games_list) AND `user` = '$user'))";
        }
    }
} else { $access_check = ''; }

$query = "SELECT `id`, `ts`, `ts_real`, `user`, `game`, `type`, `linked`, `info`, `param` FROM `game_log` WHERE 1=1 $access_check $game_filter $user_filter $type_filter ORDER BY `id` DESC LIMIT $offset, $lines_on_page";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
while ($row = mysql_fetch_array($sql))
{
    $login = query_val_by_key('login', 'user', 'id', $row['user']);
    $team = query_val_by_key('name', 'user', 'id', $row['user']);

    // Если пользователя такого уже снесли из базы,
    // определим логин, который у него был, по таблице заявок на игры
    if ($login == '')
    {
        $row2 = query_1st_row("SELECT `login` FROM `game_request` WHERE `approved` = '1' AND `game` = '{$row['game']}' AND `user` = '{$row['user']}'");
        $login = "{$row2[0]} (удален)";
    }

    // Если имя не задано, используем логин в качестве имени
    if ($team == '') { $team = $login; }

    $game_name = query_val_by_key('name', 'tree', 'id', $row['game']);
    $obj_id = $row['linked'];
    $obj = query_val_by_key('name', 'tree', 'id', $row['linked']);
    $info = $row['info'];
    $param = $row['param'];

    $color = '';

    if (is_it_group($obj_id))
    {
        $type1 = "группу";
        $type2 = "группе";
        $color = 'gray';
    } else if (is_it_task($obj_id))
    {
        $type1 = "задание";
        $type2 = "задании";
    } else {
        $type1 = "бонус";
        $type2 = "бонусе";
    }

    switch($row['type'])
    {
        case EVENT_TYPE_START_TASK:
            $msg = "Команда \"$team\" получила $type1 \"$obj\"";
            if ($color == '') { $color = 'blue'; }
        break;

        case EVENT_TYPE_HINT:
            $msg = "Команда \"$team\" получила подсказку \"$obj\"";
            $color = 'blue';
        break;

        case EVENT_TYPE_HINT_RQ:
            $msg = "Команда \"$team\" попросила подсказку \"$obj\"";
            $color = 'brown'; // fixme: cyan?
        break;

        case EVENT_TYPE_WRONG_CODE:
            $msg = "Команда \"$team\" ввела неверный код \"$info\" на $type2 \"$obj\"";
            $color = 'gray';
        break;

        case EVENT_TYPE_TIME_LIM:
            $msg = "Команда \"$team\" не успела выполнить $type1 \"$obj\" ($info)";
            $color = 'red';
        break;

        case EVENT_TYPE_END_TASK:
            $msg = "Команда \"$team\" завершила $type1 \"$obj\"";
            if ($color == '') { $color = 'orange'; }
        break;

        case EVENT_TYPE_LOGIN:
            $msg = "Команда \"$team\" вошла в систему \"$obj\"";
            $color = 'black';
        break;

        case EVENT_TYPE_LOGOUT:
            $msg = "Команда \"$team\" вышла из системы \"$obj\"";
            $color = 'black';
        break;

        case EVENT_TYPE_START_GAME:
            $msg = "Игра началась для команды \"$team\"";
            $color = 'blue';
        break;

        case EVENT_TYPE_END_GAME:
            $msg = "Игра закончилась для команды \"$team\"";
            $color = 'orange';
        break;

        case EVENT_TYPE_RIGHT_CODE:
            $msg = "Команда \"$team\" ввела верный код на $type2 \"$obj\"";
            if ($color == '') { $color = 'green'; }
        break;

        case EVENT_TYPE_END_GAME_ALL:
            $msg = "Игра закончилась";
            $color = 'orange';
        break;

        case EVENT_TYPE_WAITING:
            $msg = "Команда \"$team\" ожидает $type1 \"$obj\"";
            if ($color == '') { $color = 'magenta'; }
        break;

        case EVENT_TYPE_INFO:
            $msg = "Команда \"$team\": $info";
            $color = 'grey';
        break;

        case EVENT_TYPE_WARNING:
            $msg = "Команда \"$team\": $info";
            $color = 'red';
        break;

        case EVENT_TYPE_CODES_LEFT:
            $msg = "Команде \"$team\" осталось взять $param код(а,ов) на $type2 \"$obj\"";
            $color = 'gray';
        break;

        case EVENT_TYPE_END_ALL_TASKS:
            $msg = "Команда \"$team\" закончила все задания, кроме бонусов";
            $color = 'orange';
        break;

        case EVENT_TYPE_TASK_PASSED:
            $msg = "Команда \"$team\" выполнила $type1 \"$obj\"";
            if ($color == '') { $color = 'green'; }
        break;

        case EVENT_TYPE_TASK_SELECTION_REQUEST:
            $msg = "Команда \"$team\" получила запрос на выбор задания или подгруппы в группе \"$obj\"";
            if ($color == '') { $color = 'magenta'; }
        break;

        case EVENT_TYPE_TASK_SELECTION:
            $msg = "Команда \"$team\" ответила на запрос на выбор задания или подгруппы, выбор: \"$obj\"";
            if ($color == '') { $color = 'blue'; }
        break;

        case EVENT_TYPE_DEBUG:
            $msg = "DEBUG: $info ($login)";
            $color = 'gray';
        break;

        case EVENT_TYPE_MANUAL_ACCEPT:
            $msg = "Команде \"$team\" вручную засчитан(о) $type1 \"$obj\"";
            if ($color == '') { $color = 'green'; }
        break;

        case EVENT_TYPE_MANUAL_DECLINE:
            $msg = "Команде \"$team\" вручную слит(о) $type1 \"$obj\"";
            $color = 'red';
        break;

        case EVENT_TYPE_MANUAL_BONUS:
            $msg = "Команде \"$team\" вручную начислен бонус в $param секунд(ы), причина: $info";
            $color = 'orange';
        break;

        case EVENT_TYPE_MANUAL_PENALTY:
            $msg = "Команде \"$team\" вручную начислен штраф в $param секунд(ы), причина: $info";
            $color = 'red';
        break;

        case EVENT_TYPE_FAIL_CODE:
            $msg = "Команда \"$team\" ввела верный СЛИВ-код на $type2 \"$obj\", $type1 слит(о,а)";
            $color = 'red';
        break;
    }

    if (($row['ts'] != $row['ts_real']) && ($row['type'] != EVENT_TYPE_DEBUG))
    {
        $ts_conv = ' (усл. '.$row['ts'].')';
    } else {
        $ts_conv = '';
    }

    // fixme: hardcoded 1 (а надо проверять членство в группе администраторы)
    if ($_SESSION['user_id'] == 1)
    {
        // а вот это - опасная функция. она позволяет удалять из середины лога, оставляя то, что выше,
        // что может нарушить целостность структур данных. по умолчанию выключим.
        if (isAdmin($user))
        {
            echo "<a style=color:red href=?game=$game&param_user=$param_user&type=$type&offset=$offset&action=del&id={$row['id']}>(x)</a> ";
        }
        echo "<a style=color:red href=?game=$game&param_user=$param_user&type=$type&offset=$offset&action=del_up&id={$row['id']}>(x&uarr;)</a> ";
    }

    echo "<span style=color:$color>" . $row['id'] . " " . $row['ts_real'] . $ts_conv . ": ". $msg;
    if ($row['type'] != EVENT_TYPE_DEBUG)
    {
        echo " (игра \"$game_name\")";
    }

    echo "</span><br>";
}

$query = "SELECT COUNT(`id`) FROM `game_log` WHERE 1=1 $access_check $game_filter $user_filter $type_filter";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
$row = mysql_fetch_array($sql);
$count = $row[0];

if (($offset - $lines_on_page) >= 0 )
{
    $offset_new = $offset - $lines_on_page;
    echo " <a href=?game=$game&param_user=$param_user&type=$type&offset=$offset_new>&larr;</a> ";
}

if (($offset + $lines_on_page) < $count )
{
    $offset_new = $offset + $lines_on_page;
    echo " <a href=?game=$game&param_user=$param_user&type=$type&offset=$offset_new>&rarr;</a> ";
}
