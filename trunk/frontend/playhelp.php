<?php

include('config.php');

reco_init();

// ---------- Константы

// Типы событий в логе игры
define("EVENT_TYPE_START_TASK",                 1);   // начало выполнения задания
define("EVENT_TYPE_HINT",                       2);   // получение подсказки
define("EVENT_TYPE_HINT_RQ",                    3);   // запрос подсказки
define("EVENT_TYPE_WRONG_CODE",                 4);   // неверный код
define("EVENT_TYPE_TIME_LIM",                   5);   // лимит времени
define("EVENT_TYPE_END_TASK",                   6);   // конец выполнения задания
define("EVENT_TYPE_LOGIN",                      7);   // вход в систему
define("EVENT_TYPE_LOGOUT",                     8);   // выход из системы
define("EVENT_TYPE_START_GAME",                 9);   // начало игры
define("EVENT_TYPE_END_GAME",                   10);  // завершение игры командой
define("EVENT_TYPE_RIGHT_CODE",                 11);  // ввод верного кода
define("EVENT_TYPE_END_GAME_ALL",               12);  // завершение игры всеми командами
define("EVENT_TYPE_WAITING",                    13);  // задание ожидается
define("EVENT_TYPE_INFO",                       14);  // информационное сообщение
define("EVENT_TYPE_WARNING",                    15);  // предупреждающее сообщение
define("EVENT_TYPE_CODES_LEFT",                 16);  // осталось взять N кодов
define("EVENT_TYPE_END_ALL_TASKS",              17);  // выполнены все задания
define("EVENT_TYPE_TASK_PASSED",                18);  // задание пройдено
define("EVENT_TYPE_TASK_SELECTION_REQUEST",     19);  // запрос на выбор задания/группы
define("EVENT_TYPE_TASK_SELECTION",             20);  // выбор задания/группы
define("EVENT_TYPE_DEBUG",                      21);  // отладочное сообщение
define("EVENT_TYPE_MANUAL_ACCEPT",              22);  // ручной зачет задания
define("EVENT_TYPE_MANUAL_DECLINE",             23);  // ручной слив задания
define("EVENT_TYPE_MANUAL_BONUS",               24);  // ручной бонус
define("EVENT_TYPE_MANUAL_PENALTY",             25);  // ручной штраф
define("EVENT_TYPE_FAIL_CODE",                  26);  // слив-код (код, по которому задание сливается со штрафом)

// Режимы выдачи заданий внутри группы
define("GROUP_TYPE_SERIAL",                     1001);
define("GROUP_TYPE_PARALLEL",                   1002);
define("GROUP_TYPE_RANDOM",                     1003);
define("GROUP_TYPE_RANDOM_PRIO",                1004);
define("GROUP_TYPE_BY_USER",                    1005);

// Типы игровых объектов
define("OBJECT_TYPE_GAME",                      3);
define("OBJECT_TYPE_GROUP",                     4);
define("OBJECT_TYPE_TASK",                      5);
define("OBJECT_TYPE_HINT",                      6);

// Типы заданий
define("TASK_TYPE_TASK",                        0);
define("TASK_TYPE_BONUS",                       1);

// Номер ревизии
define("VER_MAJOR_VERSION",                     '0.3.0');
define("VER_SVN_REVISION",                      '$Rev: 447 $');

// ---------- Global variables

// Кэш

$cache = array();

// ---------- System functions

// Запись в лог события текущего пользователя по текущей игре
function write_log($type, $linked, $info = '', $param = '', $game_id = '', $user = '')
{
    $GLOBALS['cache'] = array(); // очистим кэш (fixme: неоптимально, конечно, чистить его целиком, но пока так)

    if ($user == '') { $user = $_SESSION['user_id']; }
    if (($game_id == '') && ($linked != 0) && ($linked != '')) { $game_id = get_game_by_object_id($linked); }

    query_result("INSERT INTO `game_log` (`id`, `user`, `game`, `type`, `linked`, `info`, `param`, `ts`) VALUES ('0', '$user', '$game_id', '$type', '$linked', '$info', '$param', CURRENT_TIMESTAMP())");

    $row = query_1st_row("SELECT LAST_INSERT_ID() FROM `game_log`");

    $result = $row[0];

    return $result;
}

function remove_object_from_log($object)
{
    $list = get_childs($object);
    foreach ($list as $item)
    {
        remove_object_from_log($item);
    }
    
    $query = "DELETE FROM `game_log` WHERE `linked` = '$object'";
    $sql = mysql_query($query) or die(mysql_error());
}

// Вывод блока автообновления в браузер
function auto_refresh()
{
    echo "<script src=js/refresh.js></script>";
    echo "<form><a href=# onclick=testConnection(refreshCallBack);>Обновить сейчас</a>, ";
    echo "или автоматически<input type=checkbox id=r onchange=rChange();>, ";
    echo "каждые<input id=rt size=3 onchange=rChange();><!-- onkeyup=rChange(); -->сек. ";
    echo "<span id=left></span></form><script>initRefresh();</script>";
}

// Отладочное сообщение в лог
function debug_msg($msg)
{
    // write_log здесь использовать неудобно, т.к. он чистит кэш

    $msg = $msg . ' [session id: ' . session_id() . ']';

    query_result("INSERT INTO `game_log` (`type`, `info`, `user`) VALUES ('".EVENT_TYPE_DEBUG."', '$msg', '{$_SESSION['user_id']}')");
}

// ---------- Gameplay helper functions

function check_cache($param)
{
    if (!CFG_ENABLE_CACHE) { return FALSE; }

    if (is_array($GLOBALS['cache'][$param]))
    {
        if (count($GLOBALS['cache'][$param]) == 0)
        {
            return FALSE;
        }
    } else {
        if ($GLOBALS['cache'][$param] == '')
        {
            return FALSE;
        }
    }

    return $GLOBALS['cache'][$param];
}

function update_cache($param, $value)
{
    if ($value != '')
    {
        $GLOBALS['cache'][$param] = $value;
    }
}

// Выдавалось ли указанное задание текущему пользователю?
function is_task_started($id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@is_task_started'); }

    $cache_id = "task_started:$id:$user";
    $cached = check_cache($cache_id);
    if ($cached) { return ($cached == 1); }

    $query = "SELECT `id` FROM `game_log` WHERE `user` = '$user' AND (`type` = '".EVENT_TYPE_START_TASK."' OR `type` = '".EVENT_TYPE_WAITING."' OR `type` = '".EVENT_TYPE_START_GAME."') AND `linked` = '$id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    if ($row['id'] == '') { $result = 0; } else { $result = 1; }

    update_cache($cache_id, $result);

    return ($result == 1);
}

// Выдавалось ли указанное задание текущему пользователю? (без учета ожидающих)
function is_task_actually_started($id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@is_task_started'); }

    $cache_id = "actually_started:$id:$user";
    $cached = check_cache($cache_id);
    if ($cached) { return ($cached == 1); }

    $query = "SELECT `id` FROM `game_log` WHERE `user` = '$user' AND (`type` = '".EVENT_TYPE_START_TASK."' OR `type` = '".EVENT_TYPE_START_GAME."') AND `linked` = '$id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    if ($row['id'] == '') { $result = 0; } else { $result = 1; }

    update_cache($cache_id, $result);

    return ($result == 1);
}

// Завершил ли текущий пользователь указанное задание?
function is_task_finished($id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@is_task_finished'); }

    $cache_id = "task_finished:$id:$user";
    $cached = check_cache($cache_id);
    if ($cached) { return ($cached == 1); }

    $query = "SELECT `id` FROM `game_log` WHERE `user` = '$user' AND (`type` = '".EVENT_TYPE_END_TASK."' OR `type` = '".EVENT_TYPE_END_GAME."') AND `linked` = '$id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    if ($row['id'] == '') { $result = 0; } else { $result = 1; }

    update_cache($cache_id, $result);

    return ($result == 1);
}

// Пройдено ли задание?
function is_task_passed($id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@is_task_passed'); }

    $query = "SELECT `id` FROM `game_log` WHERE `user` = '$user' AND (`type` = '".EVENT_TYPE_TASK_PASSED."' OR `type` = '".EVENT_TYPE_END_GAME."') AND `linked` = '$id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    if ($row['id'] == '') { return false; }
    return true;
}

// Выполняет ли текущий пользователь указанное задание?
function is_task_active($id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@is_task_active'); }

    if (!(is_task_started($id, $user))) { return false; }
    if (is_task_finished($id, $user)) { return false; }

    return true;
}

// Возвращает тип указанного объекта
function get_object_type($id)
{
    $cache_id = "obj_type:$id";
    $cached = check_cache($cache_id);
    if ($cached) { return ($cached); }

    $query = "SELECT `class` FROM `tree` WHERE `id` = '$id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    update_cache($cache_id, $row['class']);

    return ($row['class']);
}

// Является ли объект бонусом?
function is_it_bonus($id)
{
    $cache_id = "is_bonus:$id";
    $cached = check_cache($cache_id);
    if ($cached) { return ($cached == 1); }

    $result = '';

    // это нужно в update_tree: поскольку нам не нужно закрывать группы, в которых есть только бонусы,
    // но нужно закрывать группы выше по дереву, мы будем считать, что группы, в которых есть только бонусы,
    // сами имеют статус бонусов
    // и ещё этот функционал используется в update_group_finish_time для проверки, не бонус-группа ли это
    $is_bonus = TRUE;
    if (is_it_group($id))
    {
        $list = get_childs($id);
        foreach ($list as $item)
        {
            if (!is_it_bonus($item)) { $is_bonus = FALSE; }
        }

        if ($is_bonus) { $result = 1; } else { $result = 0; }

    } else {

        $query = "SELECT `task_type` FROM `object_data_".OBJECT_TYPE_TASK."` WHERE `id` = '$id';";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        $row = mysql_fetch_array($sql);

        if ($row['task_type'] == 1) { $result = 1; } else { $result = 0; }
    }

    update_cache($cache_id, $result);

    return ($result == 1);
}

// Является ли объект группой?
function is_it_group($id)
{
    return (get_object_type($id) == OBJECT_TYPE_GROUP);
}

// Является ли объект заданием?
function is_it_task($id)
{
    return ((get_object_type($id) == OBJECT_TYPE_TASK) && !is_it_bonus($id));
}

function is_it_game($id)
{
    return (get_object_type($id) == OBJECT_TYPE_GAME);
}

// Установить текущую игру для всей дальнейшей работы движка
function set_active_game($id)
{
    $GLOBALS['active_game'] = $id;
}

// Получить список пользователей, которые играют в эту игру
function get_users_in_game($game)
{
    if ($game == '') { debug_msg('no_game@get_users_in_game'); die; }

    $cache_id = "user_in_game:$game";
    $cached = check_cache($cache_id);
    if ($cached) { return $cached; }

    $result = array();
    $qr = query_result("SELECT `user` FROM `game_request` WHERE `approved` = '1' AND `game` = '$game';");
    while ($row = mysql_fetch_array($qr))
    {
        $result[] = $row['user'];
    }

    update_cache($cache_id, $result);

    return $result;
}

// Возвращает список активных заданий
function list_active_tasks($with_waiting_groups = false, $with_all_groups = false, $game_id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@list_active_tasks'); }
    if ($game_id == '') { debug_msg('no_game@list_active_tasks'); die; }

    $cache_id = "active_tasks:$game_id:$user";
    $cached = check_cache($cache_id);

    if ($cached)
    {
        $result = $cached;

    } else {

        $result = list_active_tasks_all($game_id, $user);
        update_cache($cache_id, $result);
    }

    $out = array();
    foreach ($result as $temp)
    {
        if (!$temp['is_group'] || ($with_waiting_groups && $temp['is_waiting']) || ($with_all_groups))
        {
            $out[] = $temp['id'];
        }
    }

    return $out;
}

// Формирует массив из массивов, каждый из которых описывает одно из активных заданий
function list_active_tasks_all($game_id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@list_active_tasks_all'); }
    if ($game_id == '') { debug_msg('no_game@list_active_tasks_all'); die; }

    $result = array();

    if (is_it_game($game_id) && !is_game_started($game_id, $user)) { return array(); }

    // Если это не игра (рекурсивный вызов), запишем в список данный объект, если он активен
    if (!is_it_game($game_id))
    {
        if (is_task_active($game_id, $user))
        {
            $is_waiting = is_task_waiting($game_id, $user);
            $is_group = is_it_group($game_id);
            $is_bonus = is_it_bonus($game_id);

            $temp = array();
            $temp['is_group'] = $is_group;
            $temp['is_waiting'] = $is_waiting;
            $temp['id'] = $game_id;
            $result[] = $temp;
        }
    }

    // Запишем дочерние объекты

    $list = get_childs($game_id);
    foreach ($list as $item)
    {
        // на finished тут не проверяем, т.к. в законченных группах могут быть незаконченные бонусы
        if (is_task_started($item, $user))
        {
            $subtree = list_active_tasks_all($item, $user);
            $result = array_merge($result, $subtree);
        }
    }

    return $result;
}

// Определяет величину задержки перед уровнем или группой в секундах
function get_start_delay($id)
{
    if (is_it_group($id)) { $delay = query_val_by_key('start_delay', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id); }
    else { $delay = query_val_by_key('start_delay', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id); }

    if ((intval($delay) > 0) && (CFG_SPEED_UP_GAME > 0))
    {
        $delay = round($delay / CFG_SPEED_UP_GAME);
    }

    return $delay;
}

// Находится ли данное задание в статусе "ожидается командой"
function is_task_waiting($id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@is_task_waiting'); }

    $cache_id = "task_waiting:$id:$user";
    $cached = check_cache($cache_id);
    if ($cached) { return ($cached == 1); }

    $query = "SELECT `id` FROM `game_log` WHERE `user` = '$user' AND `type` = '".EVENT_TYPE_WAITING."' AND `linked` = '$id'";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);
    $waiting = $row['id'];

    $query = "SELECT `id` FROM `game_log` WHERE `user` = '$user' AND `type` = '".EVENT_TYPE_START_TASK."' AND `linked` = '$id'";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);
    $started = $row['id'];

    if (($waiting != '') && ($started == '')) { $result = 1; } else { $result = 0; }

    update_cache($cache_id, $result);

    return ($result == 1);
}

// Определить политику выдачи заданий для группы
function get_group_schedule_policy($id)
{
    $cache_id = "schedule_policy:$id";
    $cached = check_cache($cache_id);
    if ($cached) { return $cached; }

    $query = "SELECT `task_type` FROM `object_data_".OBJECT_TYPE_GROUP."` WHERE `id` = '$id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    update_cache($cache_id, $row['task_type']);

    return $row['task_type'];
}

// Игра началась?
function is_game_started($game_id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@is_game_started'); }
    if ($game_id == '') { debug_msg('no_game@is_game_started'); die; }

    $cache_id = "game_started:$game_id:$user";
    $cached = check_cache($cache_id);
    if ($cached) { return ($cached == 1); }

    $query = "SELECT `id` FROM `game_log` WHERE `user` = '$user' AND `type` = '".EVENT_TYPE_START_GAME."' AND `game` = '$game_id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    if ($row['id'] == '') { $result = 0; } else { $result = 1; }

    update_cache($cache_id, $result);

    return ($result == 1);
}

// Проверяет, были ли выполнены все _основные_ задания на этой игре, без учета бонусов (по логу)
function is_all_tasks_finished($game_id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@is_all_tasks_finished'); }
    if ($game_id == '') { debug_msg('no_game@is_all_tasks_finished'); die; }

    $query = "SELECT `id` FROM `game_log` WHERE `user` = '$user' AND `type` = '".EVENT_TYPE_END_ALL_TASKS."' AND `game` = '$game_id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    return ($row['id'] != '');
}

// Выводит список незаконченных игр
function list_unfinished_games()
{
    $result = array();
    $query = "SELECT `id` FROM `object_data_".OBJECT_TYPE_GAME."` WHERE `finished` <> '1';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    while ($row = mysql_fetch_array($sql))
    {
        $result[] = $row['id'];
    }

    return $result;
}

// Есть ли невыполненные задания (не бонусы) на этой игре у этого юзера?
function if_unfinished_tasks($id, $user)
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@if_unfinished_tasks'); }
    if ($id == '') { debug_msg('no_game@if_unfinished_tasks'); die; }

    $result = FALSE;

    if (is_it_bonus($id))
    {
        return FALSE;
    }

    if (is_it_task($id))
    {
        return (!is_task_finished($id, $user));
    } else {

        $list = get_childs($id);
        foreach ($list as $item)
        {
            $result = $result || if_unfinished_tasks($item, $user);
        }

        return $result;
    }
}

function task_time_limit($id, $format = '')
{
    if ($format == '') { $format = '%H:'.'%i:'.'%s'; }

    $limit = get_limit($id);
    $row = query_1st_row("SELECT TIME_FORMAT(SEC_TO_TIME('$limit'), '$format')");

    return $row[0];
}

function task_time_limit_seconds($id)
{
    return get_limit($id);
}

// Сколько времени команда провела на задании? Возвращает массив,
// первый элемент - форматированное время, второй - в секундах.
function time_on_task($id, $format = '', $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@time_on_task'); }
    if ($format == '') { $format = '%H:'.'%i:'.'%s'; }

    // Если задание уже завершено, то у нас должно быть
    // уже посчитанное время завершения, возьмем его
    $row = query_1st_row("SELECT TIME_FORMAT(SEC_TO_TIME(`param`), '$format'), `param` FROM `game_log` WHERE `linked` = '$id' AND (`type` = ".EVENT_TYPE_END_TASK." OR `type` = ".EVENT_TYPE_END_GAME.") AND `user` = '$user'");
    if ($row[1] != '')
    {
        return array($row[0], $row[1]);

    } else {

        // Задание ещё выполняется

        $row = query_1st_row("SELECT TIMESTAMPDIFF(SECOND, `ts`, NOW()) FROM `game_log` WHERE (`type` = ".EVENT_TYPE_START_TASK." OR `type` = ".EVENT_TYPE_START_GAME.") AND `user` = '$user' AND `linked` = '$id'");
        $on_task = $row[0];

        $row = query_1st_row("SELECT TIME_FORMAT(SEC_TO_TIME('$on_task'), '$format'), '$on_task'");

        if ($row[0] == '0') { $row[0] = '00:00:00'; }

        return array($row[0], $row[1]);
    }
}

// Сколько команде осталось ждать выдачи задания?
function get_task_waiting_time($id, $format = '', $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@get_task_waiting_time'); }
    if ($format == '') { $format = '%H:'.'%i:'.'%s'; }

    $delay = get_start_delay($id);

    $query = "SELECT TIME_FORMAT(TIMEDIFF(TIMESTAMPADD(SECOND, $delay, `ts`), NOW()), '$format'), TIMESTAMPDIFF(SECOND, NOW(), TIMESTAMPADD(SECOND, $delay, `ts`)) FROM `game_log` WHERE `linked` = '$id' AND `type` = ".EVENT_TYPE_WAITING." AND `user` = '$user';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    return array($row[0], $row[1]);
}

// Получена ли подсказка командой
function is_hint_scheduled($id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@is_hint_scheduled'); }

    $query = "SELECT `id` FROM `game_log` WHERE `linked` = '$id' AND (`type` = ".EVENT_TYPE_HINT." OR `type` = ".EVENT_TYPE_HINT_RQ.") AND `user` = '$user';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    return ($row[0] != '');
}

// Выдать подсказку
function give_hint($id, $game, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@give_hint'); }
    if ($game == '') { debug_msg('no_game@give_hint'); die; }

    write_log(EVENT_TYPE_HINT, $id, '', '', $game, $user);
}

// Выдать "штрафную" подсказку
function request_hint($id, $game, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@request_hint'); }
    if ($game == '') { debug_msg('no_game@request_hint'); die; }

    // определим бонус, к которому эта подсказка
    $bonus_id = query_val_by_key('parent', 'tree', 'id', $id);

    write_log(EVENT_TYPE_HINT_RQ, $id, NULL, $bonus_id, $game, $user);
}

// Кто сейчас выполняет данное задание?
function users_on_task($task)
{
    $cache_id = "users_on_task:$task";
    $cached = check_cache($cache_id);
    if ($cached) { return $cached; }

    $result = 0;
    $query = "SELECT `user` FROM `game_log` WHERE `linked` = '$task' AND `type` = ".EVENT_TYPE_START_TASK.";";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    while ($row = mysql_fetch_array($sql))
    {
        $query2 = "SELECT `id` FROM `game_log` WHERE `user` = '{$row['user']}' AND `linked` = '$task' AND `type` = ".EVENT_TYPE_END_TASK.";";
        $sql2 = mysql_query($query2) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        $row2 = mysql_fetch_array($sql2);

        if ($row2['id'] == '')
        {
            $result++;
        }
    }

    update_cache($cache_id, $result);

    return $result;
}

function get_bonus_time($user = '', $game)
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@get_bonus_time'); }
    if ($game == '') { debug_msg('no_game@get_bonus_time'); die; }

    $result = 0;

    $qr = query_result("SELECT `id`, `linked` FROM `game_log` WHERE `user` = '$user' AND `game` = '$game' AND `type` = '".EVENT_TYPE_TASK_PASSED."';");
    while ($row = mysql_fetch_array($qr))
    {
        // если это бонус
        if (query_val_by_key('task_type', 'object_data_'.OBJECT_TYPE_TASK, 'id', $row['linked']) == 1)
        {
            $result += query_val_by_key('time_penalty', 'object_data_'.OBJECT_TYPE_TASK, 'id', $row['linked']);

            $qr2 = query_result("SELECT `id`, `linked` FROM `game_log` WHERE `user` = '$user' AND `game` = '$game' AND `type` = '".EVENT_TYPE_HINT_RQ."' AND `linked` IN (SELECT `id` FROM `tree` WHERE `parent` = {$row['linked']});");
            while ($row2 = mysql_fetch_array($qr2))
            {
                $result -= query_val_by_key('penalty', 'object_data_'.OBJECT_TYPE_HINT, 'id', $row2['linked']);
            } 

        }
    }

    $qr = query_result("SELECT `id`, `linked`, `param` FROM `game_log` WHERE `user` = '$user' AND `game` = '$game' AND `type` = '".EVENT_TYPE_MANUAL_BONUS."';");
    while ($row = mysql_fetch_array($qr))
    {
        $result += $row['param'];
    }

    return $result;
}

function get_penalty_time($user = '', $game)
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@get_penalty_time'); }
    if ($game == '') { debug_msg('no_game@get_penalty_time'); die; }

    $result = 0;

    // здесь определяется, штрафовать ли при ручном сливе задания админом, или нет
    $qr = query_result("SELECT `id`, `linked` FROM `game_log` WHERE `user` = '$user' AND `game` = '$game' AND (`type` = '".EVENT_TYPE_TIME_LIM."' OR `type` = '".EVENT_TYPE_MANUAL_DECLINE."');");
    while ($row = mysql_fetch_array($qr))
    {
        if (query_val_by_key('task_type', 'object_data_'.OBJECT_TYPE_TASK, 'id', $row['linked']) == 0)
        {
            // если это задание
            $result += query_val_by_key('time_penalty', 'object_data_'.OBJECT_TYPE_TASK, 'id', $row['linked']);
        }
    }

    $qr = query_result("SELECT `id`, `linked`, `param` FROM `game_log` WHERE `user` = '$user' AND `game` = '$game' AND `type` = '".EVENT_TYPE_MANUAL_PENALTY."';");
    while ($row = mysql_fetch_array($qr))
    {
        $result += $row['param'];
    }

    return $result;
}

function dc_by_id($id)
{
    $a = mysql_fetch_array(mysql_query("SELECT dc FROM object_data_5 WHERE id = '$id'")); // fixme: hardcoded "5"
    if (($a[0] == "") OR ($a[0] == "0")) $a[0] = "не указан";
    return $a[0];
}

function get_limit($id)
{
    $cache_id = "limit:$id";
    $cached = check_cache($cache_id);
    if ($cached)
    {
        if ($cached == 'unset') { $cached = ''; }
        return ($cached);
    }

    if (!(is_it_group($id)))
    {
        $query = "SELECT `time_limit`, `time_limit_parent` FROM `object_data_".OBJECT_TYPE_TASK."` WHERE `id` = '$id';";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        $row = mysql_fetch_array($sql);
        $limit = $row['time_limit'];
    } else {
        $query = "SELECT `time_limit`, `time_limit_parent` FROM `object_data_".OBJECT_TYPE_GROUP."` WHERE `id` = '$id';";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        $row = mysql_fetch_array($sql);
        $limit = $row['time_limit'];
    }

    if ((intval($limit) > 0) && (CFG_SPEED_UP_GAME > 0))
    {
        $limit = round($limit / CFG_SPEED_UP_GAME);
    }

    if ($limit == '')
    {
        update_cache($cache_id, 'unset');
    } else {
        update_cache($cache_id, $limit);
    }

    return $limit;
}

function get_limit_parent($id)
{
    $cache_id = "limit_parent:$id";
    $cached = check_cache($cache_id);
    if ($cached)
    {
        if ($cached == 'unset') { $cached = ''; }
        return ($cached);
    }

    if (!(is_it_group($id)))
    {
        $query = "SELECT `time_limit`, `time_limit_parent` FROM `object_data_".OBJECT_TYPE_TASK."` WHERE `id` = '$id';";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        $row = mysql_fetch_array($sql);
        $limit_parent = $row['time_limit_parent'];
    } else {
        $query = "SELECT `time_limit`, `time_limit_parent` FROM `object_data_".OBJECT_TYPE_GROUP."` WHERE `id` = '$id';";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        $row = mysql_fetch_array($sql);
        $limit_parent = $row['time_limit_parent'];
    }

    if ((intval($limit_parent) > 0) && (CFG_SPEED_UP_GAME > 0))
    {
        $limit_parent = round($limit_parent / CFG_SPEED_UP_GAME);
    }

    if ($limit_parent == '')
    {
        update_cache($cache_id, 'unset');
    } else {
        update_cache($cache_id, $limit_parent);
    }

    return $limit_parent;
}

function get_object_parent($id)
{
    $cache_id = "obj_parent:$id";
    $cached = check_cache($cache_id);
    if ($cached) { return ($cached); }

    $query = "SELECT `parent` FROM `tree` WHERE `id` = '$id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);
    $result = $row['parent'];

    update_cache($cache_id, $result);

    return $result;
}

// "Путь" до текущего задания, с указанием общего количества и количества выполненных заданий в каждой группе
function get_task_path($task, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@get_task_path'); }

    $current = $task;
    $result = '';

    while (TRUE)
    {
        if (CFG_PATHS_DISPLAY_MODE == 2)
        {
            if (get_object_parent(get_object_parent(get_object_parent($current))) == 1) { break; }
        } else {
            if (get_object_parent(get_object_parent($current)) == 1) { break; }
        }

        // защита от потенциально бесконечного цикла
        if (($current == 1) || (intval($current) == 0)) { break; }

        $current = get_object_parent($current);

        $num = 0;
        $count = 0;
        $list = get_childs($current);
        foreach ($list as $item)
        {
            if (is_task_finished($item, $user)) { $num++; }
            $count++;
        }
        $num++;

        $name = query_val_by_key('name', 'tree', 'id', $current);

        if ($num > $count) // Задание уже выполнено
        {
            $result = " &rarr; $name (завершено)" . $result;
        } else {
            $result = " &rarr; $name ($num/$count)" . $result;
        }

        if (CFG_PATHS_DISPLAY_MODE == 1) { break; }
    }

    $result = ltrim($result, ' &rarr;');

    return $result;
}


function get_hint_delay($id)
{
    $delay = query_val_by_key('delay', 'object_data_'.OBJECT_TYPE_HINT, 'id', $id);

    if ((intval($delay) > 0) && (CFG_SPEED_UP_GAME > 0))
    {
        $delay = round($delay / CFG_SPEED_UP_GAME);
    }

    return $delay;
}

function time_before_hint($task, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@time_before_hint'); }

    $delta = 0;

    $list = get_childs($task);
    foreach ($list as $item)
    {
        $time = time_on_task($task, '', $user); $time = $time[1];
        $delay = get_hint_delay($item);

        if ( ($time < $delay) && ( (($delay - $time) < $delta) || ($delta == 0) ) )
        {
            $delta = $delay - $time;
        }
    }

    return $delta;
}

function time_before_hint_js($task, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@time_before_hint_js'); }

    $counter = intval(time_before_hint($task, $user));

    if ($counter > 0)
    {
        echo "<span id=hint_cntdn_$task></span>";
        echo "<script src=js/cntdown.js></script>";
        echo "<script>display_c($counter, 'hint_cntdn_$task');</script>";
    }
}

function time_on_task_js($task, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@time_on_task_js'); }

    $counter = time_on_task($task, '', $user); $counter = $counter[1];
    $limit = intval(task_time_limit_seconds($task));

    echo "<span id=task_time_$task></span>";
    echo "<script src=js/timer.js></script>";
    echo "<script>Timer($counter, 'task_time_$task', $limit);</script>";
}

function is_it_marked_as_manual($id)
{
    if (get_object_type($id) == OBJECT_TYPE_GROUP)
    {
        return query_val_by_key('manual', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id);
    } else if (get_object_type($id) == OBJECT_TYPE_TASK) {
        return query_val_by_key('manual', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id);
    }
}

// Определить игру по id объекта в ней
function get_game_by_object_id($id)
{
    $cache_id = "game_by_id:$id";
    $cached = check_cache($cache_id);
    if ($cached) { return $cached; }

    $result = '';
    $parent = $id;
    $type = 0;

    while ($type != OBJECT_TYPE_GAME)
    {
        $type = query_val_by_key('class', 'tree', 'id', $parent);
        if ($type == OBJECT_TYPE_GAME)
        {
            $result = $parent;
            break;
            return $parent;
        }

        $parent = query_val_by_key('parent', 'tree', 'id', $parent);

        if (($parent == 0) || ($parent == '')) { $result = ''; break; }
    }

    update_cache($cache_id, $result);

    return $result;
}

// Форматирует время в секундах через запрос к MySQL
function format_seconds($sec)
{
    $row = query_1st_row("SELECT SEC_TO_TIME('$sec')");
    return $row[0];
}


// Возвращает массив id потомков объекта
function get_childs($id)
{
    $cache_id = "childs_list:$id";
    $cached = check_cache($cache_id);
    if ($cached) { return $cached; }

    $result = array();
    $sql = query_result("SELECT `id` FROM `tree` WHERE `parent` = '$id' ORDER BY `order_token`");
    while ($row = mysql_fetch_array($sql))
    {
        $result[] = $row['id'];
    }

    update_cache($cache_id, $result);

    return $result;
}

// ---------- Gameplay maintenance functions

// Проверить, не пора ли начать какую-нибудь игру
function check_start_game($game_id = '', $user = '')
{
    if (($user != '') && ($game_id != '')) { if (is_game_started($game_id, $user)) { return; } }

    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@check_start_game'); }

    $sql = query_result("SELECT `id` FROM `object_data_".OBJECT_TYPE_GAME."` WHERE `finished` <> '1' AND `start_time` <> '' AND (NOW() > STR_TO_DATE(`start_time`, '%d.%m.%Y %H:%i'))");
    while ($row = mysql_fetch_array($sql))
    {
        if ( !is_game_started($row[0], $user) && (($game_id == '') || ($game_id == $row[0])) )
        {
            // А заявка-то данного пользователя на данную игру - принята?

            $sql2 = query_result("SELECT `id` FROM `game_request` WHERE `user` = '$user' AND `approved` = 1 AND `game` = '$row[0]'");
            $row2 = mysql_fetch_array($sql2);

            if ($row2[0] != '')
            {
                // Ну, если принята, то можно начать.

                if (CFG_START_IN_REAL_TIME == 1)
                {
                    $game_start = "STR_TO_DATE((SELECT `start_time` FROM `object_data_".OBJECT_TYPE_GAME."` WHERE `id` = '{$row['id']}'), '%d.%m.%Y %H:%i')";
                } else {
                    $game_start = "CURRENT_TIMESTAMP()";
                }

                // fixme: по идее, мы можем использовать здесь write_log
                query_result("INSERT INTO `game_log` (`id`, `user`, `game`, `type`, `linked`, `ts`) VALUES ('0', '$user', '$row[0]', '".EVENT_TYPE_START_GAME."', '$row[0]', $game_start)");

                query_result("DELETE FROM `task_selection_request` WHERE `user` = '$user'");

                $GLOBALS['cache'] = array(); // очистим кэш (fixme: неоптимально, конечно, чистить его целиком, но пока так)
            }
        }
    }
}

// Выдать конкретное задание
function schedule_task($id, $user = '', $now = FALSE)
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@schedule_task'); }
    $game_id = get_game_by_object_id($id);

    if (is_task_actually_started($id, $user))
    {
        return FALSE;
    }

    $delay = get_start_delay($id);

    // если задание имеет статус "ожидается", значит, время ожидание истекло
    // иначе бы нас не вызвали
    if (($delay > 0) && (!$now) && !is_task_waiting($id, $user))
    {
        $log_record_id = write_log(EVENT_TYPE_WAITING, $id, '', '', $game_id, $user);

        $result = FALSE;

    } else {
        // hackfix: debug: session_id ***
        $sid = session_id();

        $log_record_id = write_log(EVENT_TYPE_START_TASK, $id, $sid, '', $game_id, $user);

        $result = TRUE;
    }

    // Если пересчет времени групп выключен, запишем старт следующего задания
    // одновременно с завершением предыдущего
    $group = get_object_parent($id);
    if (is_it_group($group) || is_it_game($group))
    {
        if (is_it_group($group)) {
            $group_type = query_val_by_key('task_type', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $group);
        } else {
            $group_type = GROUP_TYPE_PARALLEL;
        }
    
        if (CFG_RECALCULATE_GROUP_TIMES == 0)
        {
            $finished = array();
            $sql = query_result("SELECT `id` FROM `tree` WHERE `parent` = '$group' AND ((`class` = '".OBJECT_TYPE_GROUP."') OR (`class` = '".OBJECT_TYPE_TASK."'))");
            while ($row = mysql_fetch_array($sql))
            {
                if (!is_it_bonus($row['id']) && is_task_finished($row['id'], $user))
                {
                    $finished[] = $row['id'];
                } // время бонусов - не учитываем
            }

            if ((count($finished) > 0) && ($group_type != GROUP_TYPE_PARALLEL))
            {
                // посчитаем время начала как время окончания последнего

                $finished_id_list = implode(',', $finished);

                $sql = query_result("SELECT `ts` FROM `game_log` WHERE `type` = ".EVENT_TYPE_END_TASK." AND `user` = '$user' AND `linked` IN ($finished_id_list) ORDER BY `ts` DESC LIMIT 0, 1");
                $row = mysql_fetch_array($sql);
                $ts = $row[0];

            } else {
                // посчитаем время начала как время начала группы

                $sql = query_result("SELECT `ts` FROM `game_log` WHERE (`type` = ".EVENT_TYPE_START_TASK." OR `type` = ".EVENT_TYPE_START_GAME.") AND `user` = '$user' AND `linked` = '$group'");
                $row = mysql_fetch_array($sql);
                $ts = $row[0];
            }

            // Если ts до сих пор не определен, то это ручная выдача,
            // когда задания выдаются раньше групп. В этом случае ничего пересчитывать не надо.
            if ($ts != '')
            {
                query_result("UPDATE `game_log` SET `ts` = '$ts' WHERE `id` = '$log_record_id'");

                // если задание с задержкой выдачи, прибавим время задержки ко времени начала
                $delay = get_start_delay($id);
                if ((intval($delay) > 0) && $result)
                {
                    query_result("UPDATE `game_log` SET `ts` = TIMESTAMPADD(SECOND, $delay, `ts`) WHERE `id` = '$log_record_id'");
                }
            }
        }
    }

    if ($result)
    {
        // ручная выдача
        check_manual_schedule($id, $user, EVENT_TYPE_START_TASK);
    }

    return $result;
}

// Проверить, нет ли в списке ручной выдачи задания,
// которое следовало бы выдать после данного, и выдать его
function check_manual_schedule($finished_task, $user = '', $type = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@check_manual_schedule'); }

    $result = query_result("SELECT `id`, `task`, `type` FROM `manual_task_list` WHERE `user` = '$user' AND `linked` = '$finished_task'");
    while ($row = mysql_fetch_array($result))
    {
        if ($row['type'] == $type)
        {
            manual_schedule($row['task'], $user);

            query_result("DELETE FROM `manual_task_list` WHERE `id` = '{$row['id']}'");
        }
    }
    
}

function manual_schedule($task, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@manual_schedule'); }

    if (!is_game_started(get_game_by_object_id($task), $user)) { debug_msg('game_not_started@manual_schedule'); return; }

    if (!is_task_started($task, $user))
    {
        $parent_active == FALSE;
        $parent = $task;

        // Активируем задание/группу (и всё, что выше её по дереву) с флагом ручной активации.
        // При этом, при активации каждой группы выше по дереву (вплоть до первой активной),
        // активация идёт с флагом "ручная активации" только непосредственно для активируемых групп,
        // их подгруппы активируются рекурсивно уже без флага "ручная активация".
        // Таким образом, если в глубине одной из веток, затронутых при ручной активации конкретного задания,
        // найдется ещё одно задание, помеченное для ручной активации, оно не будет активировано до тех пор,
        // пока его не активируют вручную, или не будет активировано задание ниже по дереву, в результате чего
        // активация упомянутого задания станет неизбежной. Если вы смогли понять всё это, вы - гуру.

        // Кроме того, активировать задания правильнее вниз по дереву, а не вверх.
        // Поэтому сначала составим список заданий, требующих активации, и идущий от родителя к ребенку,
        // а затем по нему уже будем активировать задания.

        $task_list = array();

        while (!$parent_active)
        {
            if (!is_task_started($parent, $user))
            {
                $task_list = array_merge(array($parent), $task_list);
            }

            $parent = get_object_parent($parent);

            // защита от потенциально бесконечного цикла
            if ((get_object_type($parent) == OBJECT_TYPE_GAME) || ($parent == 1) || (intval($parent) == 0)) { break; }

            $parent_active = is_task_active($parent, $user);
        }

        foreach ($task_list AS $item)
        {
            schedule_task($item, $user, TRUE);
        }
    }
}

// Проверить, не закончилось ли время ожидания
// "ожидающих" заданий
function check_waiting_tasks($game_id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@check_waiting_tasks'); }
    if ($game_id == '') { debug_msg('no_game@check_waiting_tasks'); die; }

    $query = "SELECT `linked`, TIMESTAMPDIFF(SECOND, `ts`, NOW()) FROM `game_log` WHERE `user` = '$user' AND `game` = '$game_id' AND `type` = '".EVENT_TYPE_WAITING."'";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    while ($row = mysql_fetch_array($sql))
    {
        $delay = get_start_delay($row['linked']);

        if (($row[1] > $delay) && (is_task_waiting($row['linked'], $user)))
        {
            // Заблокируем пользователя на время выдачи ему заданий

            $mid = "ug:$user:$game_id";
            if (mutex_acquire($mid))
            {
                schedule_next_tasks($row['linked'], '', $user);
                mutex_release($mid);

            } else {

                debug_msg("User $user is locked, happend at check_waiting_tasks, game: $game_id");
            }
        }
    }
}

// Обойти дерево и выдать задания, требующие выдачи (параметр-игра)
function schedule_next_tasks_top($game_id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@schedule_next_tasks_top'); }

    // А он вообще начал эту игру, этот юзер?
    check_start_game($game_id, $user);

    // Потому что если нет, нефига ему задания выдавать, вдруг его заявка отклонена.
    if (!is_game_started($game_id, $user)) { return; }

    // Заблокируем пользователя на время выдачи ему заданий

    $mid = "ug:$user:$game_id";
    if (mutex_acquire($mid))
    {
        $list = get_childs($game_id);
        foreach ($list as $item)
        {
            schedule_next_tasks($item, '', $user);
        }

        mutex_release($mid);

    } else {

        debug_msg("User $user is locked, happend at schedule_next_tasks_top, game: $game_id");
    }
}

// Обойти дерево и выдать задания, требующие выдачи (параметр-поддерево: группа или задание)
function schedule_next_tasks($id, $manual = FALSE, $user = '')
{
        if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@schedule_next_tasks'); }

        // если задание/группа находится в ожидании, дальше по дереву идти не надо
        if (is_task_waiting($id, $user))
        {
            $waiting_time = get_task_waiting_time($id, '', $user);
            $waiting_time = $waiting_time[1];

            if ($waiting_time > 0)
            {
                return;
            }
        }

        // Мы выдаем задания даже из законченных групп -
        // там могут оставаться несделанные бонусы.
        // Поэтому здесь нет проверки вроде is_task_finished($id).
        if (is_it_group($id))
        {

            // для всех активных объектов в группе: проверим, не нужно ли что-нибудь выдать внутри них
            $list = get_childs($id);
            foreach ($list as $item)
            {
                if (is_task_active($item, $user))
                {
                    schedule_next_tasks($item, '', $user);
                }
            }

            // если группа помечена как "только для ручной выдачи", и не активна - больше ничего делать не надо
            $manual_only = query_val_by_key('manual', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id);
            if (($manual_only == 1) && !is_task_active($id, $user) && !$manual) { return; }

            // нас могли вызвать, только если нужно стартануть этот объект
            // из schedule_next_tasks_top вызов только при старте игры,
            // а из schedule_next_tasks - только для неначатых заданий в группах, где нет активных
            // поэтому сразу отметим в логе, что эта группа стартовала
            schedule_task($id, $user);

            $policy = get_group_schedule_policy($id);

            // Политика выдачи по умолчанию: в случайном порядке без приоритетов
            if (intval($policy) == 0) { $policy == GROUP_TYPE_RANDOM; }

            switch ($policy)
            {
                case GROUP_TYPE_SERIAL:

                    // выдаем первое по порядку задание, если в группе нет активного

                    $next_task = '';

                    $list = get_childs($id);
                    foreach ($list as $item)
                    {
                        if (is_task_active($item, $user)) { $next_task = ''; break; }
                        if (!(is_task_finished($item, $user)) && ($next_task == '')) { $next_task = $item; }
                    }

                    if (($next_task != '') && (is_it_marked_as_manual($next_task) != '1'))
                    {
                        schedule_next_tasks($next_task, '', $user);
                    }

                    break;

                case GROUP_TYPE_PARALLEL:
                    // выдаем все сразу

                    $list = get_childs($id);
                    foreach ($list as $item)
                    {
                        if (!is_task_started($item, $user) && (is_it_marked_as_manual($item) != '1'))
                        {
                            schedule_next_tasks($item, '', $user);
                        }
                    }

                    break;

                case GROUP_TYPE_RANDOM:

                    $group_not_busy = true;
                    $tasks = array();
                    $list = get_childs($id);
                    foreach ($list as $item)
                    {
                        if (is_task_active($item, $user)) { $group_not_busy = false; break; }
                        if (!is_task_finished($item, $user) && (is_it_marked_as_manual($item) != '1')) { $tasks[] = $item; }
                    }

                    // выдача в первую очередь наиболее свободных заданий
                    $tasks = filter_task_list_by_min_users($tasks);

                    if (($group_not_busy) && (count($tasks) > 0))
                    {
                        $idx = array_rand($tasks, 1);
                        schedule_next_tasks($tasks[$idx], '', $user);
                    }

                    break;

                case GROUP_TYPE_RANDOM_PRIO:

                    $limit1 = query_val_by_key('prio_max', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id);
                    $limit2 = query_val_by_key('prio_mid', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id);
                    $limit3 = $limit1 + $limit2;

                    // в случайном порядке, из подгруппы с приоритетом MAX
                    $group_not_busy = true;
                    $tasks = array();
                    $query2 = "SELECT `id` FROM `tree` WHERE `parent` = '$id' ORDER BY order_token LIMIT 0, $limit1;";
                    $sql2 = mysql_query($query2) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
                    while ($row2 = mysql_fetch_array($sql2))
                    {
                        if (is_task_active($row2['id'], $user)) { $group_not_busy = false; break; }
                        if (!is_task_finished($row2['id'], $user) && (is_it_marked_as_manual($row2['id']) != '1')) { $tasks[] = $row2['id']; }
                    }

                    // выдача в первую очередь наиболее свободных заданий
                    $tasks = filter_task_list_by_min_users($tasks);

                    // уже есть активные задания в этой группе? выходим.
                    if (!($group_not_busy)) { break; }
                    if (count($tasks) > 0)
                    {
                        $idx = array_rand($tasks, 1);
                        schedule_next_tasks($tasks[$idx], '', $user);
                        break;
                    }

                    // в случайном порядке, из подгруппы с приоритетом MID
                    $group_not_busy = true;
                    $tasks = array();
                    $query2 = "SELECT `id` FROM `tree` WHERE `parent` = '$id' ORDER BY order_token LIMIT $limit1, $limit2;";
                    $sql2 = mysql_query($query2) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
                    while ($row2 = mysql_fetch_array($sql2))
                    {
                        if (is_task_active($row2['id'], $user)) { $group_not_busy = false; break; }
                        if (!is_task_finished($row2['id'], $user) && (is_it_marked_as_manual($row2['id']) != '1')) { $tasks[] = $row2['id']; }
                    }

                    // выдача в первую очередь наиболее свободных заданий
                    $tasks = filter_task_list_by_min_users($tasks);

                    // уже есть активные задания в этой группе? выходим.
                    if (!($group_not_busy)) { break; }
                    if (count($tasks) > 0)
                    {
                        $idx = array_rand($tasks, 1);
                        schedule_next_tasks($tasks[$idx], '', $user);
                        break;
                    }

                    // в случайном порядке из оставшихся
                    $group_not_busy = true;
                    $tasks = array();
                    // hackfix: hardcoded 1000
                    $query2 = "SELECT `id` FROM `tree` WHERE `parent` = '$id' ORDER BY order_token LIMIT $limit3, 1000;";
                    $sql2 = mysql_query($query2) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
                    while ($row2 = mysql_fetch_array($sql2))
                    {
                        if (is_task_active($row2['id'], $user)) { $group_not_busy = false; break; }
                        if (!is_task_finished($row2['id'], $user) && (is_it_marked_as_manual($row2['id']) != '1')) { $tasks[] = $row2['id']; }
                    }

                    // выдача в первую очередь наиболее свободных заданий
                    $tasks = filter_task_list_by_min_users($tasks);

                    // уже есть активные задания в этой группе? выходим.
                    if (!($group_not_busy)) { break; }
                    if (count($tasks) > 0)
                    {
                        $idx = array_rand($tasks, 1);
                        schedule_next_tasks($tasks[$idx], '', $user);
                        break;
                    }

                    break;

                case GROUP_TYPE_BY_USER:

                    // проверим, нет ли выполняющихся заданий в группе

                    $next_task = '';
                    $list = get_childs($id);
                    foreach ($list as $item)
                    {
                        if (is_task_active($item, $user)) { $next_task = ''; break; }
                        if (!is_task_finished($item, $user) && (is_it_marked_as_manual($item) != '1') && ($next_task == ''))
                        {
                            $next_task = $item;
                        }
                    }

                    if ($next_task != '')
                    {
                        // проверим, нет ли необработанных запросов на выбор
                        
                        $query2 = "SELECT `id` FROM `task_selection_request` WHERE `user` = '$user'";
                        $sql2 = mysql_query($query2) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
                        $row2 = mysql_fetch_array($sql2);
                        if ($row2['id'] == '')
                        {
                            // если нет, создадим новый запрос
                            $query2 = "INSERT INTO `task_selection_request` (`user`, `group`) VALUES ('$user', '$id')";
                            $sql2 = mysql_query($query2) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);

                            $game_id = get_game_by_object_id($id);
                            write_log(EVENT_TYPE_TASK_SELECTION_REQUEST, $id, '', '', $game_id, $user);
                        }
                    }

                    break;
            }

        } else
        {
            $manual_only = query_val_by_key('manual', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id);
            if (($manual_only != 1) || ($manual))
            {
                schedule_task($id, $user);
            }
        }
}

// фильтрация списка заданий таким образом, чтобы в нем остались только те,
// на которых присутствует минимальное количество команд
function filter_task_list_by_min_users($tasks)
{
    $min_users = '';
    $users_on_tasks_local = array();

    // определим минимальное количество команд, которое сейчас есть на каком-либо задании
    foreach ($tasks as $task)
    {
        $users_on_tasks_local[$task] = users_on_task($task);
        if (($users_on_tasks_local[$task] < $min_users) || ($min_users == ''))
        {
            $min_users = $users_on_tasks_local[$task];
        }
    }

    // составим новый список заданий, включая туда только те,
    // на которых есть команд не более определенного ранее минимального числа
    $tasks_new = array();
    foreach ($tasks as $task)
    {
        if ($users_on_tasks_local[$task] <= $min_users) { $tasks_new[] = $task; }
    }
    
    return $tasks_new;
}

// зафиксируем завершение групп, в которых завершены все задания
//
// эта функция не будет путать порядок в логах,
// т.к. сначала рекурсивно вызывается она сама,
// а затем уже пишется запись в логи,
// таким образом, в логи сначала попадает запись самого глубокого уровня,
// что и требуется

function update_tree($id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@update_tree'); }
    $game_id = get_game_by_object_id($id);

    // если это не группа, дальше идти не надо
    if (!is_it_group($id) && !is_it_game($id)) { return; }

    $finished = array();
    $nonfinished_tasks = 0;
    $nonfinished_bonuses = 0;
    $task_count = 0;
    $query = "SELECT `id` FROM `tree` WHERE `parent` = '$id' AND ((`class` = '".OBJECT_TYPE_GROUP."') OR (`class` = '".OBJECT_TYPE_TASK."'));";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    while ($row = mysql_fetch_array($sql))
    {
        // пройдемся по подгруппам
        update_tree($row['id'], $user);

        // посчитаем общее число заданий, а также число незавершенных заданий и бонусов
        // при этом группы, в которых есть только бонусы, мы тоже считаем бонусами,
        // т.к. завершения таких групп не требуется для завершения родительской группы.
        // поскольку is_it_bonus следует этой логике,
        // мы можем использовать её как для заданий, так и для групп

        // бонус-группы здесь не учитываем
        $bonus_flag = is_it_bonus($row['id']);
        $finished_flag = is_task_finished($row['id'], $user);

        if (!$bonus_flag) { $task_count++; }

        if (!$finished_flag)
        {
            if ($bonus_flag) { $nonfinished_bonuses++; } else { $nonfinished_tasks++; }
        }
    }

    // если эта группа уже помечена как завершенная, нам тут делать нечего
    if (is_task_finished($id, $user)) { return; }

    // завершим группу, если в ней есть задания, и все они выполнены,
    // или же если в ней только бонусы, и все они выполнены

    if ((($task_count == 0) && ($nonfinished_bonuses == 0)) || (($task_count > 0) && ($nonfinished_tasks == 0)))
    {
        $type = query_val_by_key('class', 'tree', 'id', $id);

        // не будем выводить завершение объекта "игра" как завершение группы в лог
        if ($type != OBJECT_TYPE_GAME)
        {
            // считать время завершения не нужно, т.к. update_group_finish_time всё равно его пересчитает
            // (по времени последнего задания или по сумме, в зависимости от типа группы и режима движка)

            $log_record_id = write_log(EVENT_TYPE_END_TASK, $id, '', '', $game_id, $user);
            update_group_finish_time($id, $log_record_id, $user);

            // ручная выдача
            check_manual_schedule($id, $user, EVENT_TYPE_END_TASK);

            // проверим, не нужно ли слить что-нибудь по ЗРГ
            $list = get_childs($id);
            foreach ($list as $item)
            {
                check_task_timed_out($item, $user);

                // update_group_finish_time здесь делать не надо,
                // т.к. завершение по ЗРГ применимо только к бонусам
                // (для обычных заданий родительская группа не завершится,
                // пока не завершено задание), а бонусы не влияют на итоговое время
            }
        }
    }
}

function write_task_finish_time($id, $user, $log_record_id)
{
    $row = query_1st_row("SELECT `param` FROM `game_log` WHERE `id` = '$log_record_id'");
    $on_task = $row[0];

    $row = query_1st_row("SELECT TIMESTAMPADD(SECOND, '$on_task', `ts`) FROM `game_log` WHERE `type` = ".EVENT_TYPE_START_TASK." AND `user` = '$user' AND `linked` = '$id'");
    $ts = $row[0];

    query_result("UPDATE `game_log` SET `ts` = '$ts' WHERE `id` = '$log_record_id'");
}

// Установить время завершения группы по сумме времени, проведенного на заданиях группы
// (для последовательной выдачи) или по завершению последнего задания группы
// (для параллельной выдачи)
function update_group_finish_time($id, $log_record_id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@update_group_finish_time'); }

    $group_type = query_val_by_key('task_type', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id);

    $object_type = query_val_by_key('class', 'tree', 'id', $id);
    if ($object_type == OBJECT_TYPE_GAME) { $group_type = GROUP_TYPE_PARALLEL; }

    $finished = array();
    $finished_bonuses = array();
    $query = "SELECT `id` FROM `tree` WHERE `parent` = '$id' AND ((`class` = '".OBJECT_TYPE_GROUP."') OR (`class` = '".OBJECT_TYPE_TASK."'));";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    while ($row = mysql_fetch_array($sql))
    {
        if (!is_it_bonus($row['id'])) { $finished[] = $row['id']; } else { $finished_bonuses[] = $row['id']; }
    }

    // если выполненных заданий нет (это бонусная группа), то будем считать бонусы заданиями
    if (count($finished) == 0) { $finished = $finished_bonuses; }

    // если выдача параллельная, то завершение считается по завершению последнего задания в группе
    // если выдача последовательная, надо посчитать сумму времён, затраченных на каждое задания группы,
    // и прибавить к времени начала группы

    // если в конфиге запрещен пересчет времени групп, то они все считаются как параллельные,
    // т.е. завершение по завершению последнего задания

    if (($group_type == GROUP_TYPE_PARALLEL) || (CFG_RECALCULATE_GROUP_TIMES == 0))
    {
        $finished_id_list = implode(',', $finished);
        $query = "SELECT `ts` FROM `game_log` WHERE `type` = ".EVENT_TYPE_END_TASK." AND `user` = '$user' AND `linked` IN ($finished_id_list) ORDER BY `ts` DESC LIMIT 0, 1";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        $row = mysql_fetch_array($sql);
        $ts = $row['ts'];

        // сразу считаем время прохождения и пишем его в param
        $row = query_1st_row("SELECT TIMESTAMPDIFF(SECOND, `ts`, '$ts') FROM `game_log` WHERE `linked` = '$id' AND (`type` = ".EVENT_TYPE_START_TASK." OR `type` = ".EVENT_TYPE_START_GAME.") AND `user` = '$user';");
        $param = $row[0];

        $query = "UPDATE `game_log` SET `ts` = '$ts', `param` = '$param' WHERE `id` = '$log_record_id';";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);

        $GLOBALS['cache'] = array(); // очистим кэш (fixme: неоптимально, конечно, чистить его целиком, но пока так)

    } else {

        $time_on_group = 0;
        foreach ($finished as $finished_task)
        {
            $temp = time_on_task($finished_task, '', $user);
            $time_on_group += $temp[1];
        }

        $row = query_1st_row("SELECT `ts` FROM `game_log` WHERE `type` = ".EVENT_TYPE_START_TASK." AND `user` = '$user' AND `linked` = '$id';");
        $ts = $row['ts'];

        // сразу считаем время прохождения и пишем его в param
        $query = "UPDATE `game_log` SET `ts` = TIMESTAMPADD(SECOND, '$time_on_group', '$ts'), `param` = '$time_on_group' WHERE `id` = '$log_record_id';";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);

        $GLOBALS['cache'] = array(); // очистим кэш (fixme: неоптимально, конечно, чистить его целиком, но пока так)
    }
}

// Проверяет все активные задания на просрочку
//
// Здесь не указанный пользователь
// подразумевает необходимость проверить всех
//
function check_active_tasks_timeout($game_id, $user = '', $do_not_finish_game = 0)
{
    if ($game_id == '') { debug_msg('no_game@check_active_tasks_timeout'); die; }

    if ($user != '') { $users_list = array($user); } else
    {
        $users_list = get_users_in_game($game_id);
    }

    // Проверим на сливы
    foreach ($users_list as $user_id)
    {
        // Заблокируем пользователя, пока будем проверять его на сливы

        $mid = "ug:$user_id:$game_id";
        if (mutex_acquire($mid))
        {
            $timeouts = 0;

            $tasks = list_active_tasks(true, true, $game_id, $user_id);
            foreach ($tasks as $task)
            {
                if (check_task_timed_out($task, $user_id, 1)) { $timeouts++; }
            }

            if ($timeouts > 0)
            {
                update_tree($game_id, $user_id);

                if ($do_not_finish_game == 0)
                {
                    is_game_finished($user_id, $game_id);
                }
            }

            mutex_release($mid);

        } else {

            debug_msg("User $user_id is locked, happend at check_active_tasks_timeout, game: $game_id");
        }
    }
}

// Вышло ли время, отпущенное на указанное задание/бонус? true - вышло
// Для бонуса - с учётом параметра "Лимит времени после ЗРГ"
// Если вышло - пишем про это в лог
function check_task_timed_out($id, $user = '', $local = 0)
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@check_task_timed_out'); }

    $result = false;
    $reason = '';

    if (!(is_task_active($id, $user))) { return false; }

    // определим тип

    $task_type = query_val_by_key('task_type', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id);

    if ($task_type == '') { $task_type = 0; }

    // задание и бонус

    $time_temp = time_on_task($id, '', $user);
    $seconds = $time_temp[1];

    $limit = get_limit($id);
    $limit_parent = get_limit_parent($id);

    // fixme: hardcoded text
    if (($seconds >= $limit) && ($limit > 0)) { $result = TRUE; $reason = 'Превышено ограничение времени выполнения'; }

    if ($result)
    {
        // здесь ts можно не указывать, потому что для случая стандартного завершения
        // по таймауту ts посчитается в task_timeout

        task_timeout($id, $reason, '', $user, '', $local);
        return $result;
    }

    // только для бонусов: проверим, не вылезли ли мы за родительский лимит

    if (($limit_parent != '') && ($limit_parent != '-1'))
    {
        $parent = get_object_parent($id);

        $query = "SELECT TIMESTAMPDIFF(SECOND, `ts`, NOW()) FROM `game_log` WHERE `user` = '$user' AND `type` = '".EVENT_TYPE_END_TASK."' AND `linked` = '$parent';";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        $row = mysql_fetch_array($sql);
        $seconds = $row[0];

        // fixme: hardcoded text
        if (($seconds != '') && ($seconds >= $limit_parent)) { $result = TRUE; $reason = 'Лимит времени с момента завершения родительской группы'; }
    }

    if ($result)
    {
        $query = "SELECT TIMESTAMPADD(SECOND, '$limit_parent', `ts`) FROM `game_log` WHERE `user` = '$user' AND `type` = '".EVENT_TYPE_END_TASK."' AND `linked` = '$parent';";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        $row = mysql_fetch_array($sql);
        $ts = $row[0];

        task_timeout($id, $reason, $ts, $user, '', $local);
        return $result;
    }

    return FALSE;
}

// Фактическая запись в лог сообщений об истечении времени на задании
// (если группа - завершаются все вложенные группы и задания)
// Если параметр ts не указан, то это обычный слив по таймауту
// (и все вложенные объекты, соответственно, сливаются по их таймаутами,
// у кого таймауты есть; у кого нет - используют родительский таумаут)
// Если указан, то это слив по ЗРГ, и все вложенные сливаются по тамауту родителей.
function task_timeout($id, $reason, $ts, $user = '', $first_call = 1, $local = 0, $ts_parent = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@task_timeout'); }
    $game_id = get_game_by_object_id($id);

    // Для начала: если группа или задание ещё не запущена(о), мы сразу её запускаем, чтобы тут же слить
    if (!is_it_game($id))
    {
        schedule_task($id, $user, TRUE);
    } else {
        // А если это игра, и она не началась - дальше делать ничего не надо.
        if (!is_game_started($id, $user)) { return; }
    }

    // если ts не указан, то будем считать его как время начала + лимит на задание
    // но если это игра - у неё нет лимита, так что оставим ts пустым,
    // в этом случае (и в случае, когда у группы нет лимита времени)
    // время игры/группы посчитается после завершения вложенных элементов
    // функцией update_group_finish_time
    $limit = get_limit($id);
    if (($ts == '') && !is_it_game($id) && $limit > 0)
    {
        $query = "SELECT TIMESTAMPADD(SECOND, '$limit', `ts`) FROM `game_log` WHERE `user` = '$user' AND `type` = '".EVENT_TYPE_START_TASK."' AND `linked` = '$id';";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        $row = mysql_fetch_array($sql);

        $ts = $row[0];
    }

    // если и лимит не указан, используем ts завершения родителя
    if ($ts == '')
    {
        $ts = $ts_parent;
    }

    if ((is_it_group($id)) || (is_it_game($id)))
    {
        // Если это группа, то нужно "слить" все подгруппы и задания из неё

        // Завершим вложенные элементы, причем сначала - активные
        $list = get_childs($id);
        $non_active_tasks = array();
        foreach ($list as $item)
        {
            if (is_task_active($item, $user))
            {
                // fixme: hardcoded text
                task_timeout($item, 'Лимит времени выполнения родительской группы', '', $user, 0, '', $ts);
            } else { $non_active_tasks[] = $item; }
        }
        foreach ($non_active_tasks as $item)
        {
            // fixme: hardcoded text
            task_timeout($item, 'Лимит времени выполнения родительской группы', '', $user, 0, '', $ts);
        }
    }

    // Если это группа или задание, запишем в лог о завершении по таймауту,
    // а если игра - проверим её на завершенность и получим время завершения
    if (!is_it_game($id))
    {
        // Если задание уже завершено (например, идет слив по ЗРГ
        // всех заданий группы, а часть уже выполнена), то ничего делать не надо
        if (is_task_finished($id, $user)) { return; }

        // Если ts всё ещё не указан, возьмем реальное количество секунд на задании
        if ($ts == '')
        {
            $time_temp = time_on_task($id, '', $user);
            $on_task = $time_temp[1];
        } else {
            $row = query_1st_row("SELECT TIMESTAMPDIFF(SECOND, `ts`, '$ts') FROM `game_log` WHERE `type` = ".EVENT_TYPE_START_TASK." AND `user` = '$user' AND `linked` = '$id'");
            $on_task = $row[0];
        }

        if (!is_it_group($id) && $limit > 0)
        {
            // FIXME! для слива по ЗРГ этого делать не надо. и для бонусов - тоже (в случае слива по ЗРГ это актуально).
            // FIXME! и ещё. фиксировать NOW() во время старта движка, и везде юзать его. чтобы секундные дельты не скапливались.
            // проверим, не в будущем ли время слива
            // такое может быть в следующих ситуациях:
            // 1. ручное завершение игры (ничего делать не надо, т.к. игра завершиться,
            // и глюков, связанных с выдачей в будущем, не будет)
            // 2. ручной слив
            // 3. слив-код
            // для случая 1 тип задания будет "игра" или "группа",
            // так что в этот if эта ситуация не попадет.
            // для остальных случаев сделаем финт ушами:
            // сольем текущим временем, но начислим штраф с разницей
            // между текущим временем на задние и лимитом на его выполнение
            // (если назначен лимит, если нет - просто сольем текущим временем)
            $row = query_1st_row("SELECT TIMESTAMPDIFF(SECOND, '$ts', NOW())");
            $timeout_in_future = $row[0];

            if ($timeout_in_future < 0)
            {
                $time_temp = time_on_task($id, '', $user);
                $real_time_on_task = $time_temp[1];

                $delta = $limit - $real_time_on_task;
                $on_task = $real_time_on_task;

                $task_name = getName($id);

                write_log(EVENT_TYPE_MANUAL_PENALTY, $id, "Коррекция времени за слив задания \"$task_name\"", $delta, $game_id, $user);
            }
        }

        $id1 = write_log(EVENT_TYPE_TIME_LIM, $id, $reason, $on_task, $game_id, $user);
        $id2 = write_log(EVENT_TYPE_END_TASK, $id, '', $on_task, $game_id, $user);

        $GLOBALS['cache'] = array(); // очистим кэш (fixme: неоптимально, конечно, чистить его целиком, но пока так)

        if (is_it_group($id))
        {
            // fixme: checkthis: а надо ли это делать, если мы к этому моменту
            // уже знаем время, по которому нужно завершать группу?
            // всегда ли оно равно тому, которое посчитает update_group_finish_time?
            // не всегда: сумма таймаутов заданий группы может быть больше таймаута группы.
            // как сливать в такой ситуации? ставить группе сумму таймаутов заданий, или таймаут группы?
            // сумма таймаутов заданий может быть в будущем, в такой ситуации нужно оставить группе её таймаут.
            update_group_finish_time($id, $id1, $user);
            update_group_finish_time($id, $id2, $user);
        } else {
            // fixme: дважды идет пересчет: сначала ts->param, затем param->ts
            // может, сразу передавать в task_timeout param, а не ts?
            write_task_finish_time($id, $user, $id1);
            write_task_finish_time($id, $user, $id2);
        }

        // ручная выдача
        check_manual_schedule($id, $user, EVENT_TYPE_END_TASK);

        // Если это первый вызов (не рекурсивный), завершим группы,
        // в которых завершены все задания (ведь совсем не обязательно
        // пытаться это сделать при каждом вложенном рекурсивном вызове:
        // в конце концов они будут завершены все и разом).

        // После этого проверим, не закончилась ли игра,
        // если нас специально не просили этого не делать

        if (($first_call == 1) && ($local == 0))
        {
            update_tree($game_id, $user);
            is_game_finished($user, $game_id);
        }

        // hackfix for bucky
        write_message(mysql_real_escape_string('Задание "'.getName($id).'" слито'), 'red');

    } else {

        is_game_finished($user, $game_id);
    }
}

// Есть ли в логе запись об окончании игры?
function is_game_finished_in_log($user, $game_id)
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@is_game_finished_in_log'); }
    if ($game_id == '') { debug_msg('no_game@is_game_finished_in_log'); die; }

    $query = "SELECT `id` FROM `game_log` WHERE `user` = '$user' AND `type` = '".EVENT_TYPE_END_GAME."' AND `game` = '$game_id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    return ($row['id'] != '');
}

// Закончена ли игра (по умолчанию - текущая) пользователем (по умолчанию - текущим)
function is_game_finished($user = '', $game_id, $check_all_users = TRUE)
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@is_game_finished'); }
    if ($game_id == '') { debug_msg('no_game@is_game_finished'); die; }

    // если игра не начиналась, то она и закончится не могла
    if (!(is_game_started($game_id, $user))) { return false; }

    // если в логе уже есть завершение этой игры, то просто вернем true
    if (is_game_finished_in_log($user, $game_id))
    {
        clear_messages();
        return true;
    }

    // проверим, не вышло ли время на одном из заданий
    check_active_tasks_timeout($game_id, $user, 1);

    // убедимся, что все задания, которые можно было выдать, выданы
    schedule_next_tasks_top($game_id, $user);

    // убедимся, что выданы "ожидающие" задания
    check_waiting_tasks($game_id, $user);

    // получим список активных заданий
    $active = list_active_tasks(true, true, $game_id, $user);

    // разница между if_unfinished_tasks и is_all_tasks_finished в том,
    // что первая реально проверяет, нет ли незаконченных заданий,
    // а вторая смотрит, нет ли в базе записи о том, что все задания выполнены.

    // Проверим, остались ли невыполненные задания

    $unfinished = if_unfinished_tasks($game_id, $user);

    // проверим, не закончены ли все основные задания (кроме бонусов)
    if (!is_all_tasks_finished($game_id, $user))
    {
        $all_tasks_finished = TRUE;
        foreach ($active as $task)
        {
            // если это основное задание, т.е. не бонус
            if (is_it_task($task)) { $all_tasks_finished = FALSE; }
        }

        // Если среди активных - только бонусы, или активных нет вообще,
        // и, к тому же, нет невыполненных - зафиксируем это в логе.
        if (($all_tasks_finished) && (!$unfinished))
        {
            $log_record_id = write_log(EVENT_TYPE_END_ALL_TASKS, $game_id, '', '', $game_id, $user);
            update_group_finish_time($game_id, $log_record_id, $user);
        }
    }

    if (($unfinished) && (count($active) == 0))
    {
        // Активных нет, а невыполненные есть.
        // Игрок, видимо, выпал из движка.
        return false;
    }

    // если их нет, зафиксируем окончание игры
    if (count($active) == 0)
    {
        $log_record_id = write_log(EVENT_TYPE_END_GAME, $game_id, '', '', $game_id, $user);
        update_group_finish_time($game_id, $log_record_id, $user);

        if ($check_all_users)
        {
            // проверим, не закончили ли игру все игроки
            check_if_game_is_finished_by_all($game_id);
        }

        clear_messages(); 
        return true;
    }

    return false;
}

function check_if_game_is_finished_by_all($game_id)
{
    if (is_game_finished_by_all($game_id)) { return; }

    $finished = true;

    $user_list = get_users_in_game($game_id);
    foreach ($user_list as $user_id)
    {
        if (!is_game_finished($user_id, $game_id, FALSE)) { $finished = false; }
    }

    // если закончили, запишем в лог, что игра окончена
    if ($finished)
    {
        // fixme: по идее, мы можем использовать здесь write_log
        $query = "INSERT INTO `game_log` (`id`, `user`, `game`, `type`, `linked`, `ts`) VALUES ('0', '{$_SESSION['user_id']}', '$game_id', '".EVENT_TYPE_END_GAME_ALL."', '$game_id', CURRENT_TIMESTAMP());";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);

        $query = "UPDATE `object_data_".OBJECT_TYPE_GAME."` SET `finished` = 1 WHERE `id` = '$game_id';";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);

        $GLOBALS['cache'] = array(); // очистим кэш (fixme: неоптимально, конечно, чистить его целиком, но пока так)
    }
}

// Закончена ли игра всеми играющими в неё командами?
function is_game_finished_by_all($game_id)
{
    $query = "SELECT `id` FROM `game_log` WHERE `type` = '".EVENT_TYPE_END_GAME_ALL."' AND `game` = '$game_id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    return ($row['id'] != '');
}

// Выдать подсказки с выдачей по времени
// (стандартные подсказки к заданиям)
function schedule_hints($game_id, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@schedule_hints'); }
    if ($game_id == '') { debug_msg('no_game@schedule_hints'); die; }

    $tasks = list_active_tasks('', '', $game_id, $user);
    foreach ($tasks as $task)
    {
        $list = get_childs($task);
        foreach ($list as $item)
        {
            $time = time_on_task($task, '', $user); $time = $time[1];
            $delay = get_hint_delay($item);

            if (($time >= $delay) && ($delay > 0))
            {
                if (!is_hint_scheduled($item, $user)) { give_hint($item, $game_id, $user); }
            }
        }
    }
}

// ---------- Code-related functions

// Проверить код ($log - писать ли в лог результаты проверки)
// Результат: FALSE - неверный, 1 - верный, -1 - слив-код
// fixme: boolean-параметры со значениями по умолчанию дают двусмысленность
// (если передать FALSE и если ничего не передать, подразумевая TRUE по умолчанию -
// фактически передастся одно и то же значение, FALSE)
function check_code($task, $code, $log = TRUE, $check_if_task_active = TRUE, $user = '', $game_id)
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@check_code'); }
    if ($game_id == '') { debug_msg('no_game@check_code'); die; }

    if ($check_if_task_active && !is_task_active($task, $user)) { return false; }

    // Пустые коды не принимаем
    if (normalize_code($code) == '') { return false; }

    $codes = explode(',', query_val_by_key('code', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task));
    $result = FALSE;

    foreach ($codes as $right_code)
    {
        // проверим слив-коды
        if (substr($right_code, 0, 1) == '!')
        {
            if (check_individual_code($code, $right_code))
            {
                $result = -1;
                break;
            }
        }

        // проверим обычные коды
        if (check_individual_code($code, $right_code)) { $result = 1; break; }
    }

    if ($log)
    {
        if (abs($result) == 1)
        {
            // если этот код уже введен, в логи писать ничего не надо!
            if (code_already_entered($code, $game_id, $user, $task)) { return false; }

            // то же самое для слив-кодов
            if (code_already_entered('!'.$code, $game_id, $user, $task)) { return false; }

            // если это слив-код
            if ($result == -1)
            {
                // здесь ts можно не считать, т.к. случай аналогичен обычному завершению задания
                // по таймауту (а в этом случае ts считается в task_timeout)

                // fixme: писать в лог факт ввода слив-кода отдельным событием, EVENT_TYPE_FAIL_CODE

                task_timeout($task, "причина: ввод СЛИВ-кода", '', $user);

            } else {

                write_log(EVENT_TYPE_RIGHT_CODE, $task, normalize_code($code), '', $game_id, $user);

                $left = codes_left_on_task($task, $user) - 1;

                // если нет, значит, это обычный код.
                if ($left > 0)
                {
                    write_log(EVENT_TYPE_CODES_LEFT, $task, NULL, $left, $game_id, $user);
                } else {

                    // сразу считаем время прохождения и пишем его в param
                    $time_temp = time_on_task($task, '', $user);
                    $param = $time_temp[1];

                    $id1 = write_log(EVENT_TYPE_TASK_PASSED, $task, '', $param, $game_id, $user);
                    $id2 = write_log(EVENT_TYPE_END_TASK, $task, '', $param, $game_id, $user);
                    write_task_finish_time($task, $user, $id1);
                    write_task_finish_time($task, $user, $id2);

                    // ручная выдача
                    check_manual_schedule($task, $user, EVENT_TYPE_END_TASK);

                    update_tree($game_id, $user);
                    is_game_finished($user, $game_id);
                }
            }
        } else {
            // ввод неверного кода

            // пишем в лог
            write_log(EVENT_TYPE_WRONG_CODE, $task, normalize_code($code), '', $game_id, $user);

            // проверим, не ввели ли код с другого задания
            $c = check_for_code_from_other_task($game_id, $code, $task, $user);
            if ($c != 0)
            {
                $fail_code_flag = FALSE;
                if ($c < 0) { $c = abs($c); $fail_code_flag = TRUE; }

                if (is_task_started($c, $user) && !is_task_waiting($c, $user))
                {
                    $type = EVENT_TYPE_INFO; $active = 'выданного';
                } else {
                    $type = EVENT_TYPE_WARNING; $active = 'НЕ выданного';
                }

                $name = query_val_by_key('name', 'tree', 'id', $c);
                $name_current = query_val_by_key('name', 'tree', 'id', $task);

                if ($fail_code_flag) { $log_code = 'СЛИВ-код'; $log_code2 = 'СЛИВ-коду'; } else { $log_code = 'код'; $log_code2 = 'коду'; }

                if (code_similarity($c, $code) <= CFG_CODE_SIMILARITY_THRESHOLD) { $reason = " похожий на $log_code "; } else { $reason = " равный $log_code2 "; }

                $msg = "На задании \"$name_current\" введен код \"$code\", $reason с $active задания \"$name\"";
            
                write_log($type, $task, $msg, '', $game_id, $user);
            }
        }
    }

    return $result;
}

// Не вводился ли такой код раньше?
function code_already_entered($code, $game_id, $user = '', $task = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@code_already_entered'); }
    if ($game_id == '') { debug_msg('no_game@code_already_entered'); die; }

    $qr = query_result("SELECT `info`, `linked` FROM `game_log` WHERE `game` = '$game_id' AND `user` = '$user' AND `type` = '".EVENT_TYPE_RIGHT_CODE."';");
    while ($row = mysql_fetch_array($qr))
    {
        if (($task == '') || ($task == $row['linked']))
        {
            if (check_individual_code($code, $row['info'])) { return true; }
        }
    }

    return false;
}

// Непосредственное сравнение кода с правильным кодом
function check_individual_code($code, $right_code)
{
    return (strcmp(normalize_code($code), normalize_code($right_code)) == 0);
}

// Нормализация кода
function normalize_code($code)
{
    // нормализованные коды у нас в верхнем регистре
    $result = strtoupper($code);

    // уберем пробелы, табуляции и переводы строк
    $result = preg_replace('/\s*/m', '', $result);

    // уберем запятые, их в верном коде быть не может, т.к.
    // они используются для разделения составных кодов
    $result = str_replace(',', '', $result);

    // уберем всяческие кавычки и тэги от греха подальше
    $result = str_replace("'", '', $result);
    $result = str_replace('"', '', $result);
    $result = str_replace('`', '', $result);
    $result = str_replace('<', '', $result);
    $result = str_replace('>', '', $result);

    // слэши тоже уберем: если от пользователя пришла кавычка,
    // она должна была заэкранироваться в \', потом кавычку мы убрали -
    // остался слэш, а он может ломать запросы
    $result = str_replace("\\", '', $result);

    // уберем восклицательные знаки, их в верном коде быть не может, т.к.
    // они используются для пометки слив-кодов
    $result = str_replace('!', '', $result);

    return $result;
}

// Сколько всего кодов на этом задании?
function codes_total_on_task($task)
{
    $count = 0;
    $codes = explode(',', query_val_by_key('code', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task));
    foreach ($codes as $code)
    {
        // слив-коды (начинаются с '!') не учитываем
        if (substr($code, 0, 1) != '!')
        {
            $count++;
        }
    }
    return $count;
}

// Сколько осталось взять кодов на этом задании?
function codes_left_on_task($task, $user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@codes_left_on_task'); }

    // запросим минимальное число кодов, оставшихся невзятыми на данном задании
    // минимальное - это в смысле среди всех записей "команде осталось n кодов на задании таком-то"
    $result = query_1st_row("SELECT `param` FROM `game_log` WHERE `user` = '$user' AND `type` = '".EVENT_TYPE_CODES_LEFT."' AND `linked` = '$task' ORDER BY `param` LIMIT 0, 1");
    $left = $result['param'];

    // если ни разу не считалось, сколько осталось кодов, посчитаем, сколько их всего на этом задании
    if ($left == '')
    {
        $left = codes_total_on_task($task);
    }

    return $left;
}

// Не введен ли, часом, код с другого задания?
// Здесь id - id игры, т.е. корневой точки поддерева,
// которое мы будем просматривать в поисках похожих кодов
function check_for_code_from_other_task($id, $code, $task, $user)
{
    if ($user == '') { $user = $_SESSION['user_id']; debug_msg('session@check_for_code_from_other_task'); }
    $game_id = get_game_by_object_id($id);

    if (query_val_by_key('class', 'tree', 'id', $id) != OBJECT_TYPE_TASK)
    {
        $list = get_childs($id);
        foreach ($list as $item)
        {
            $c = check_for_code_from_other_task($item, $code, $task, $user);
            if ($c != 0) { return $c; }
        }
    }
    else {
        if ($id == $task) { return 0; }

        $check_result = check_code($id, $code, FALSE, FALSE, $user, $game_id);
        if (($check_result) || (code_similarity($id, $code) <= CFG_CODE_SIMILARITY_THRESHOLD))
        {
            if ($check_result == -1) { return 0 - $id; } else { return $id; }
        }
    }

    return 0;
}

// Возвращает количество несовпадающих символов в коде
// Проверяет все коды данного задания, возвращает минимальное число несовпадающих символов
// Если коды разной длины, возвращает false. Т.е. сравнению подлежат только коды одной длины
// Слив-коды сравниваются также, как и обычные, т.е. "!" отсекаются.
function code_similarity($task, $code)
{
    $codes = explode(',', query_val_by_key('code', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task));
    $result = false;

    $differences = '';
    foreach ($codes as $right_code)
    {
        $right_code = normalize_code($right_code);
        $code = normalize_code($code);

        // проверим соответствие кодов вида 023, 024 и 0231
        $diffs_local = 0;
        $max_len = max(strlen($code), strlen($right_code));
        for ($i = 0, $j = $max_len; $i < $j; $i++)
        {
            if ($code[$i] != $right_code[$i]) { $diffs_local++; }
        }
        if (($diffs_local < $differences) || ($differences == '')) { $differences = $diffs_local; }

        // проверим соответствие кодов вида 023 и 23
        $code_temp = $code; $code_temp = str_repeat(' ', CFG_CODE_SIMILARITY_THRESHOLD) . $code_temp;
        $diffs_local = 0;
        $max_len = max(strlen($code_temp), strlen($right_code));
        for ($i = 0, $j = $max_len; $i < $j; $i++)
        {
            if ($code_temp[$i] != $right_code[$i]) { $diffs_local++; }
        }
        if (($diffs_local < $differences) || ($differences == '')) { $differences = $diffs_local; }

        // проверим соответствие кодов вида 23 и 023
        $code_temp = $right_code; $code_temp = str_repeat(' ', CFG_CODE_SIMILARITY_THRESHOLD) . $code_temp;
        $diffs_local = 0;
        $max_len = max(strlen($code), strlen($code_temp));
        for ($i = 0, $j = $max_len; $i < $j; $i++)
        {
            if ($code[$i] != $code_temp[$i]) { $diffs_local++; }
        }
        if (($diffs_local < $differences) || ($differences == '')) { $differences = $diffs_local; }

    }

    return $differences;
}

// Проверка, есть ли коды такой же длинны, как $code, на указанном задании
function check_code_length($task, $code)
{
    $codes = explode(',', query_val_by_key('code', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task));
    foreach ($codes as $right_code)
    {
        if (strlen(normalize_code($right_code)) == strlen(normalize_code($code))) { return TRUE; }
    }

    return FALSE;
}

function is_content_empty($string)
{
    $temp = strip_tags($string, '<map><area><img>');
    $temp = preg_replace('/\s*/m', '', $temp);

    return ($temp == '');
}

function finish_game($game)
{
    foreach (get_users_in_game($game) as $user)
    {
        // fixme: если игра для команды даже не начиналась, просто отменяем заявку?

        if (!is_game_started($game, $user)) { check_start_game($game, $user); }

        // fixme: нужна ли здесь эта проверка?
        if (!is_game_finished($user, $game, FALSE))
        {
            task_timeout($game, 'Ручная остановка игры', '', $user);
        }
    }

    check_if_game_is_finished_by_all($game);
}

// ---------- System functions

function reco_init()
{
    mutex_init();
}

function convert_to_int()
{
    foreach(func_get_args() as $a)
    {
        if ($GLOBALS[$a] != '')
        {
            $converted = intval($GLOBALS[$a]);

            if (strval($converted) != strval($GLOBALS[$a]))
            {
                debug_msg("Invalid value \'{$GLOBALS[$a]}\' for parameter \'$a\'");

                $GLOBALS[$a] = $converted;
            }
        }
    }
}

// ---------- Mutex functions

function mutex_init()
{
    // создадим таблицу, если ещё не создана
    mysql_unbuffered_query("CREATE TABLE IF NOT EXISTS `mutex` (`keystr` varchar(24) UNIQUE, `ts` TIMESTAMP) ENGINE=MEMORY");

    // удалим "подвисшие" мьютексы
    // fixme: hardcoded 30
    mysql_unbuffered_query("DELETE FROM `mutex` WHERE TIMESTAMPDIFF(SECOND, `ts`, NOW()) > 30");
}

function mutex_acquire($key)
{
    // Если удалось добавить новую запись - мьютекс наш, иначе - такой мьютекс уже есть, т.е. занят.
    // И пусть теперь mysql следит за тем, чтобы два одновременных мьютекса создать было нельзя.
    $done = ($result = mysql_unbuffered_query("INSERT INTO `mutex` (`keystr`) VALUES ('$key')"));

    return $done;
}

function mutex_release($key)
{
    query_result("DELETE FROM `mutex` WHERE `keystr` = '$key'");
}


// ---------- Message functions

// Выдать однократное сообщение пользователю
function send_message($msg, $color, $url = '?')
{
    write_message($msg, $color);

    exit_with_redirect($url);
}

// Фактическая запись сообщения в базу
function write_message($msg, $color)
{
    $query = "INSERT INTO `messages` (`id`, `text`, `color`, `user`) VALUES (0, '$msg', '$color', '{$_SESSION['user_id']}');";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
}

// Данное сообщение уже показывалось ранее?
function message_shown($id)
{
    $query = "DELETE FROM `messages` WHERE `id` = '$id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
}

// Очистить историю сообщений
function clear_messages()
{
    $query = "DELETE FROM `messages` WHERE `user` = '{$_SESSION['user_id']}';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
}

// ---------- Constants functions

function get_available_events()
{
    $result = array();
    $constants = get_defined_constants(TRUE);
    $user_constants = $constants['user'];
    foreach ($user_constants as $constant => $value)
    {
        if (substr($constant, 0, 11) == 'EVENT_TYPE_') { $result[$constant] = $value; }
    }
    return $result;
}

function get_version()
{
    $rev = preg_replace('/\D/', '', VER_SVN_REVISION);
    return VER_MAJOR_VERSION.', rev. '.$rev;
}
