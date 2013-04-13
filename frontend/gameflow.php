<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../libs/globaltree/');
include('helpers.php');
include('playhelp.php');

initAuth();

// Подключаем либу для мягких переносов
set_include_path(get_include_path() . PATH_SEPARATOR . '../libs/hypher/');
include('hypher.php');

// Создание объекта, загрузка файла описания и набора правил
$hy_ru = new phpHypher('../libs/hypher/hyph_ru_RU.conf');

// контроль времени выполнения
if (CFG_LOG_EXECUTION_TIME == 1) { $start_time = microtime_float(); }

header("Content-Type: text/html; charset=utf-8");
query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

// аутентификация
checkAuth('../admin/php/login.php?back=../../frontend/index.php');
$user = $_SESSION['user_id'];

secureGetRequestData('game', 'action', 'parameter', 'param_user', 'param_task', 'param_linked', 'param_info', 'param_type');
convert_to_int('game', 'parameter', 'param_user', 'param_task', 'param_linked', 'param_type');

// Проверка безопасности
if (($game == '') || !is_it_game($game))
{
    die;
}

// Не закончилась ли, нафиг, игра?
// check_if_game_is_finished_by_all($game);
//
// Нам не обязательно здесь делать фактическую проверку, не закончили ли игру все команды,
// потому что она делается в is_game_finished при завершении игры командой, а она, в свою очередь,
// вызывается из task_timeout, которая вызывается из check_task_timed_out, которую вызывает
// check_active_tasks_timeout, которую вызовем, если в логе нет записи о завершении игры всеми.
// Поэтому ограничимся проверкой флага в логе.

if (!is_game_finished_by_all($game))
{
    // Не вышло ли время по одному из текущих заданий?
    // Если вышло - засчитаем слив.
    // При этом новых заданий выдавать не будем
    // (человек выпал из движка -> задания дальше не выдаются).
    // fixme: slow

    check_active_tasks_timeout($game);
}

// после check_active_tasks_timeout($game) нужно перепроверить, не закончилась ли игра
$current_game_is_finished_by_all = is_game_finished_by_all($game);

$edit_mode = TRUE;
$owner = query_val_by_key('owner', 'tree', 'id', $game);
if (($owner != $user) && !isAdmin($user))
{
    // текущий юзер - не хозяин игры, значит, это игрок зашёл посмотреть статистику

    if (!$current_game_is_finished_by_all)
    {
        // если игра ещё идёт - статистика закрыта
        die('Нельзя смотреть статистику в процессе игры, а также управлять игрой, которая создана не вами (если вы, конечно, не админ).');
    }

    // окей, игра закончена, тогда включаем режим просмотра статистики
    $edit_mode = FALSE;
}

// Всё, права доступа проверены, можно работать.
// Стоп! Если это агент, то у него должен быть read-only доступ!

$welcome = query_val_by_key('login', 'user', 'id', $_SESSION['user_id']);
if ($welcome == 'agent')
{
    $edit_mode = FALSE;
} 

// Вот теперь действительно можно работать.

// Выполним необходимые действия

if ($action == 'force_refresh')
{
    if ($edit_mode)
    {
        $query = "UPDATE `game_request` SET `force_refresh` = '1' WHERE `user` = '$parameter' AND `game` = '$game';";
        $sql = mysql_query($query) or die(mysql_error());
        exit_with_redirect("?game=$game");
    }
}

if ($action == 'cancel_req')
{
    if ($edit_mode)
    {
        $query = "DELETE FROM `game_request` WHERE `user` = '$parameter' AND `game` = '$game';";
        $sql = mysql_query($query) or die(mysql_error());
        exit_with_redirect("?game=$game");
    }
}

if ($action == 'stop_game')
{
    if ($edit_mode && !$current_game_is_finished_by_all)
    {
        finish_game($game);
        exit_with_redirect("?game=$game");
    }
}

if ($action == 'manual_schedule')
{
    if ($edit_mode && !$current_game_is_finished_by_all)
    {
        if ($param_type == 0)
        {
            if (!is_game_started(get_game_by_object_id($param_task), $param_user))
            {
                echo "<meta http-equiv=refresh content=2;url=?game=$game>Ошибка! Команда ещё не начала игру (<a href=?game=$game>возврат</a> через 2 секунды)";
                die;
            }

            manual_schedule($param_task, $param_user);

        } else {

            if (!is_task_started($param_task, $param_user))
            {
                $row = query_1st_row("SELECT COUNT(`id`) FROM `manual_task_list` WHERE `user` = '$param_user' AND `task` = '$param_task' AND `linked` = '$param_linked' AND `game` = '$game' AND `type` = '$param_type'");
                if ($row[0] > 0)
                {
                    echo "<meta http-equiv=refresh content=2;url=?game=$game>Ошибка! Повторное назначение. (<a href=?game=$game>возврат</a> через 2 секунды)";
                    die;
                }

                $query = "INSERT INTO `manual_task_list` (`user`, `task`, `linked`, `game`, `type`) VALUES ('$param_user', '$param_task', '$param_linked', '$game', '$param_type')";
                $sql = mysql_query($query) or die(mysql_error());
            }
        }

        exit_with_redirect("?game=$game");
    }
}

if ($action == 'kill_manual')
{
    if ($edit_mode)
    {
        $query = "DELETE FROM `manual_task_list` WHERE `id` = '$parameter'";
        $sql = mysql_query($query) or die(mysql_error());
    }

    exit_with_redirect("?game=$game");
}

if ($action == 'manual_accept')
{
    if (($edit_mode) && is_task_active($param_task, $param_user) && is_it_task($param_task))
    {
        write_log(EVENT_TYPE_MANUAL_ACCEPT, $param_task, '', '', $game, $param_user);

        $time_temp = time_on_task($param_task, '', $param_user);
        $param = $time_temp[1];

        $id1 = write_log(EVENT_TYPE_TASK_PASSED, $param_task, '', $param, $game, $param_user);
        $id2 = write_log(EVENT_TYPE_END_TASK, $param_task, '', $param, $game, $param_user);
        write_task_finish_time($param_task, $param_user, $id1);
        write_task_finish_time($param_task, $param_user, $id2);

        // ручная выдача
        check_manual_schedule($param_task, $param_user, EVENT_TYPE_END_TASK);

        update_tree($game, $param_user);
        is_game_finished($param_user, $game);

        exit_with_redirect("?game=$game");

    } else {

        echo "<meta http-equiv=refresh content=2;url=?game=$game>Запрещено (<a href=?game=$game>возврат</a> через 2 секунды)";
        die;
    }
}

if ($action == 'manual_decline')
{
    // if (($edit_mode) && is_task_active($param_task, $param_user) && is_it_task($param_task))
    if (($edit_mode) && is_task_active($param_task, $param_user))
    {
        write_log(EVENT_TYPE_MANUAL_DECLINE, $param_task, '', '', $game, $param_user);

        task_timeout($param_task, "Причина: ручное завершение задания", '', $param_user);

        $msg = 'Выполнено';
    } else { $msg = 'Запрещено'; }

    exit_with_redirect("?game=$game");
}

if ($action == 'full_erase')
{
    if ($edit_mode && !$current_game_is_finished_by_all)
    {
        remove_object_from_log($param_task);
        removeObject($param_task, TRUE, TRUE);

        exit_with_redirect("?game=$game");
    }
}

if ($action == 'manual_bonus')
{
    if ($edit_mode)
    {
        write_log(EVENT_TYPE_MANUAL_BONUS, '', $param_info, $parameter, $game, $param_user);

        exit_with_redirect("?game=$game");
    }
}

if ($action == 'manual_penalty')
{
    if ($edit_mode)
    {
        write_log(EVENT_TYPE_MANUAL_PENALTY, '', $param_info, $parameter, $game, $param_user);

        exit_with_redirect("?game=$game");
    }
}

if ($action == 'kill_manual_bp')
{
    if ($edit_mode)
    {
        $event_type = query_val_by_key('type', 'game_log', 'id', $parameter);
        if (($event_type == EVENT_TYPE_MANUAL_BONUS) || ($event_type == EVENT_TYPE_MANUAL_PENALTY))
        {
            query_result("DELETE FROM `game_log` WHERE `id` = '$parameter' AND `game` = '$game'");
        }

        exit_with_redirect("?game=$game");
    }
}

/*
if ($action == 'manual_prolongate')
{
    if ($edit_mode && !$current_game_is_finished_by_all)
    {
        $level_name = getName($param_task);

        // Теоретически, можно сразу назначать штраф аналогичного времени как-нибудь так:
        // $msg = "Штраф за ручное продление времени на прохождение уровня $level_name";
        // write_log(EVENT_TYPE_MANUAL_PENALTY, $param_task, $msg, $parameter, $game, $param_user);
        // Но на практике это обычно не требуется, а штраф в подобной ситуации при необходимости можно назначить вручную.

        $msg = "Время выполнения задания \"$level_name\" вручную продлено на $parameter секунд";
        write_log(EVENT_TYPE_INFO, $param_task, $msg, $parameter, $game, $param_user);

        query_result("UPDATE `game_log` SET `ts` = TIMESTAMPADD(SECOND, $parameter, `ts`) WHERE `linked` = '$param_task' AND `type` = '".EVENT_TYPE_START_TASK."'");

        exit_with_redirect("?game=$game");
    }
}
*/

// Все действия выполнены
echo "Добро пожаловать, $welcome! <a href=../admin/php/login.php?logout&back=../../frontend/index.php>Выход</a><br>";

// дизайн
echo '<link rel="stylesheet" href="css/tech.css" type="text/css">';

// Выведем блок автообновления
auto_refresh();

// Выведем статистику

// Составим списки id участников, их имен и логинов
// (а если игра закончена - то также составим список времени прохождения)
$users = get_users_in_game($game);
$user_names = array();
$user_logins = array();
$top_times = array();
$game_finished_by_all = is_game_finished_by_all($game);
foreach ($users as $cur_user)
{
    $name = query_val_by_key('name', 'user', 'id', $cur_user);
    $login = query_val_by_key('login', 'user', 'id', $cur_user);

    // Если пользователя такого уже снесли из базы,
    // определим логин, который у него был, по таблице заявок на игры
    if ($login == '')
    {
        $row = query_1st_row("SELECT `login` FROM `game_request` WHERE `approved` = '1' AND `game` = '$game' AND `user` = '$cur_user'");
        $login = "{$row[0]} (удален)";
    }

    // Если имя не задано, используем логин в качестве имени
    if ($name == '') { $name = $login; }

    $user_names[$cur_user] = $name;
    $user_logins[$cur_user] = $login;

    // Если игра закончена, посчитаем время завершения игры пользователем
    if ($game_finished_by_all)
    {
        $p = get_penalty_time($cur_user, $game);
        $b = get_bonus_time($cur_user, $game);

        $row = query_1st_row("SELECT UNIX_TIMESTAMP(`ts`) FROM `game_log` WHERE `user` = '$cur_user' AND `game` = '$game' AND `type` = '".EVENT_TYPE_START_GAME."';");
        $from = $row[0];

        $row = query_1st_row("SELECT UNIX_TIMESTAMP(`ts`) FROM `game_log` WHERE `user` = '$cur_user' AND `game` = '$game' AND `type` = '".EVENT_TYPE_END_ALL_TASKS."';");
        $to = $row[0];

        $result = $to - $from - $b + $p;

        $top_times[] = $result;
    }
}

$users_sorted = $users;

// Если игра окончена, выведем итоги
if ($game_finished_by_all)
{
    array_multisort($top_times, $users_sorted);

    // итоги игры
    $cnt = 0;
    $game_name = getName($game);
    echo "<b>Игра \"$game_name\" окончена, результаты:</b><br><br><table>";
    echo "<tr><td>Место</td><td>Игрок</td><td>Время</td></tr>";
    foreach ($users_sorted as $cur_user)
    {
        $row = query_1st_row("SELECT SEC_TO_TIME({$top_times[$cnt]});");
        $result = $row[0];

        $time_temp = time_on_task($game, '', $cur_user);
        $time = $time_temp[0];
        $time_sec = $time_temp[1];

        // Посчитаем время старта и финиша
        $row = query_1st_row("SELECT TIME_FORMAT(`ts`, '%H:%i:%s'), `ts` FROM `game_log` WHERE `game` = '$game' AND `user` = '$cur_user' AND `linked` = '$game' AND (`type` = '".EVENT_TYPE_START_TASK."' OR `type` = '".EVENT_TYPE_START_GAME."')");
        $task_start_time = $row[0];
        $row = query_1st_row("SELECT TIME_FORMAT(TIMESTAMPADD(SECOND, '$time_sec', '{$row[1]}'), '%H:%i:%s')");
        $finish_time = $row[0];

        $result .= "&nbsp;&nbsp;&nbsp;<small>($time без учета бонусов и штрафов, $task_start_time - $finish_time)</small>";

        $cnt_pp = $cnt + 1;
        echo "<tr><td>$cnt_pp</td><td>{$user_names[$cur_user]}</td><td>$result</td></tr>";

        $cnt++;
    }
    echo "</table><br>";
}

admin_table_style();
echo "<table border=1>";

$user_list = '';
$user_list .= "<tr><td></td>";
foreach ($users_sorted as $user)
{
    // Расстановка переносов
    $name_hy = $hy_ru->hyphenate($user_names[$user], 'utf-8');

    $online = query_val_by_key('session_id', 'session', 'user_id', $user);

    if ($online == '')
    {
        $color_s = '<font color=orange>';
        $color_e = '</font>';
    } else {
        $color_s = '<font color=green>';
        $color_e = '</font>';
    }

    $cancel = '';
    if (!is_game_started($game, $user) && $edit_mode)
    {
        $cancel = "<a href=?game=$game&action=cancel_req&parameter=$user title=\"Отменить заявку на игру\">";
        $cancel .= "<font color=red>(x)</font></a>";
    }

    $user_list .= "<td>$color_s <b>$name_hy</b> <br>";
    if ($user_logins[$user] != $user_names[$user])
    {
        $user_list .= "<font size=1>({$user_logins[$user]})</font>";
    }
    $user_list .= " $color_e $cancel";

    if ($edit_mode)
    {
        $user_list .= " <a href=?game=$game&action=force_refresh&parameter=$user title=\"Включить пользователю автообновление\">";
        $user_list .= "<font color=blue>(&crarr;)</font></a>";
    }

    $user_list .= "</td>";
}
$user_list .= "</tr>";

echo $user_list;

$plain_task_list = array();
$object_names = array();
$object_classes = array();
$task_types = array();
$finished_tasks_count = array();
$current_task_times = array();
list_tasks($game, 0);

echo "<tr><td><b>Штрафы</b></td>";
foreach ($users_sorted as $user)
{
    $time = get_penalty_time($user, $game);
    $row = query_1st_row("SELECT SEC_TO_TIME($time);");
    $time = $row[0];
    echo "<td style=\"color:red\">$time</td>";
}
echo "</tr>";

echo "<tr><td><b>Бонусы</b></td>";
foreach ($users_sorted as $user)
{
    $time = get_bonus_time($user, $game);
    $row = query_1st_row("SELECT SEC_TO_TIME($time);");
    $time = $row[0];
    echo "<td style=\"color:green\"><nobr>$time</nobr></td>";
}
echo "</tr>";

echo "<tr><td><b>Итого</b></td>";
foreach ($users_sorted as $user)
{
    // Поскольку $top_times считается только при завершении игры всеми командами,
    // здесь нужно считать время заново.

    $p = get_penalty_time($user, $game);
    $b = get_bonus_time($user, $game);

    $row = query_1st_row("SELECT UNIX_TIMESTAMP(`ts`) FROM `game_log` WHERE `user` = '$user' AND `game` = '$game' AND `type` = '".EVENT_TYPE_START_GAME."';");
    $from = $row[0];

    $row = query_1st_row("SELECT UNIX_TIMESTAMP(`ts`) FROM `game_log` WHERE `user` = '$user' AND `game` = '$game' AND `type` = '".EVENT_TYPE_END_ALL_TASKS."';");
    $to = $row[0];

    $result = $to - $from - $b + $p;

    $row = query_1st_row("SELECT SEC_TO_TIME($result);");
    $result = $row[0];

    if (!is_game_started($game, $user)) { $result = 'Игра не началась'; }
    else if (!is_game_finished_in_log($user, $game)) { $result = 'Игра идёт'; }

    echo "<td>$result</td>";
}
echo "</tr>";

// бонусы бывают за выполнения бонусных заданий
// (за вычетом взятых бонусных подсказок)
// штрафы - за слив заданий

// Повторим список пользователей

echo $user_list;

// Если игра ещё идет, выведем количество завершенных заданий
if (!is_game_finished_by_all($game))
{
    echo "<tr><td>Завершено заданий, время на последнем</td>";
    foreach ($users_sorted as $user)
    {
        $last_task_time = '';
        if ($current_task_times["$user"] != '') { $last_task_time = ", {$current_task_times["$user"]}"; }
        if ($finished_tasks_count["$user"] == '') { $finished_tasks_count["$user"] = 0; }
        echo "<td>{$finished_tasks_count["$user"]}$last_task_time</td>";
    }
    echo "</tr>";
}

echo "</table>";

function list_tasks($id, $level)
{
    GLOBAL $hy_ru, $plain_task_list, $object_names, $object_classes, $task_types, $finished_tasks_count, $current_task_times, $users, $users_sorted, $game;

    if ($object_names[$id] == '')
    {
        $row = query_1st_row("SELECT `name`, `class` FROM `tree` WHERE `id` = '$id'");

        $row['name'] = $hy_ru->hyphenate($row['name'], 'utf-8');

        $object_names[$id] = $row['name'];
        $object_classes[$id] = $row['class'];
    }

    // бонусом или не бонусом может быть только объект типа задание
    if ($object_classes[$id] == OBJECT_TYPE_TASK)
    {
        if ($task_types[$id] == '')
        {
            if (is_it_bonus($id)) { $task_types[$id] = 1; } else { $task_types[$id] = 0; }
        }
    }

    $blod_s = '';
    $blod_e = '';

    $it_is_game = FALSE;
    if ($object_classes[$id] == OBJECT_TYPE_GROUP)
    {
        $type_name = 'Группа'; $deco_s = '<b><font color=lightgray>'; $deco_e = '</font></b>';
    } else if ($object_classes[$id] == OBJECT_TYPE_GAME) {
        $it_is_game = TRUE;
        $type_name = 'Игра'; $deco_s = '<b>'; $deco_e = '</b>';
    } else if ($task_types[$id] == 1) {
        $type_name = 'Бонус'; $deco_s = '<i>'; $deco_e = '</i>';
    } else {
        $type_name = 'Задание';
    }

    if ($object_classes[$id] != OBJECT_TYPE_HINT)
    {
        $plain_task_list[] = $id;

        echo "<tr><td>";

        // Расстановка переносов
        // $object_names[$id] = $hy_ru->hyphenate($object_names[$id], 'utf-8');
        // $type_name = $hy_ru->hyphenate($type_name, 'utf-8');

        // Для бонусов сразу покажем бонусное время
        $bonus_time = '';
        if ($task_types[$id] == 1)
        {
            $bonus_time = query_val_by_key('time_penalty', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id);
            $bonus_time = format_seconds($bonus_time);
            $bonus_time = " ($bonus_time)";
        }

        for ($i = 1; $i <= $level; $i++) { echo '<font color=gray>&middot;</font>'; }
        echo "$deco_s $type_name \"{$object_names[$id]}\" $bonus_time $deco_e</td>";

        // Заранее закэшируем время выполнения законченных заданий, чтобы лишний раз не лазать в базу
        $format = '%H:'.'%i:'.'%s';
        $finish_times = array();
        $sql = query_result("SELECT TIME_FORMAT(SEC_TO_TIME(`param`), '$format'), `param`, `user` FROM `game_log` WHERE `linked` = '$id' AND (`type` = ".EVENT_TYPE_END_TASK." OR `type` = ".EVENT_TYPE_END_GAME.")");
        while ($row = mysql_fetch_array($sql))
        {
            // если по каким-то причинам param не посчитан, кэшировать ничего не надо
            // время посчитается далее функцией time_on_task
            if ($row[1] != '')
            {
                $temp = array();
                $temp['formatted'] = $row[0];
                $temp['seconds'] = $row[1];
                $finish_times["$row[2]"] = $temp;
            }
        }

        foreach ($users_sorted as $user)
        {
            $started = is_task_started($id, $user);

            // если задание не началось, нет смысла считать время его прохождения
            if ($started)
            {
                if ($finish_times["$user"])
                {
                    $time = $finish_times["$user"]['formatted'];
                    $time_sec = $finish_times["$user"]['seconds'];
                    $finished = TRUE;
                } else {                    
                    $time_temp = time_on_task($id, '', $user); $time = $time_temp[0]; $time_sec = $time_temp[1];
                    $finished = FALSE;
                }

                // считаем завершенные задания
                if ($finished && ($object_classes[$id] == OBJECT_TYPE_TASK) && ($task_types[$id] != 1))
                {
                    if ($finished_tasks_count["$user"] == '') { $finished_tasks_count["$user"] = 1; }
                    else { $finished_tasks_count["$user"]++; }
                }

                // Определим необходимые пиктограммы часов.
                // Только для заданий, для групп, бонусов и всей игры - пока не вижу смысла.
                // Если больше трёх часов - тоже часики не выводим, а то плывёт верстка.

                if (($object_classes[$id] == OBJECT_TYPE_TASK) && ($task_types[$id] != 1) && ($time_sec < 10800))
                {
                    $min = floor($time_sec / 60);
                    $clock = '';
                    while ($min >= 60)
                    {
                        $min = $min - 60;
                        $clock .= "<img style=\"margin:2px; padding:0;\" src=icons/60.png>";
                    }
                    $icon = 0;
                    while ($min >= 5)
                    {
                        $min = $min - 5;
                        $icon = $icon + 5;
                    }

                    if ($icon == 5) { $icon = '05'; }
                    if ($icon == 0)
                    {
                        if ($clock == '')
                        {
                            $clock = "<img style=\"margin:2px; padding:0;\" src=icons/00.png>";
                        }
                    } else
                    {
                        $icon = 'icons/' . $icon . '.png';
                        $clock .= "<img style=\"margin:2px; padding:0;\" src=$icon>";
                    }
                } else if (($object_classes[$id] == OBJECT_TYPE_TASK) && ($task_types[$id] != 1) && ($time_sec >= 10800))
                {
                    $clock = '<img style=\"margin:2px; padding:0;\" src=icons/3p.png>';
                } else
                {
                    $clock = '';
                }

                $time = $deco_s . $time . $deco_e;
            }

            $red_flag = FALSE;

            if ($started)
            {
                // Посчитаем время старта и финиша
                $row = query_1st_row("SELECT TIME_FORMAT(`ts`, '%H:%i:%s'), `ts` FROM `game_log` WHERE `game` = '$game' AND `user` = '$user' AND `linked` = '$id' AND (`type` = '".EVENT_TYPE_START_TASK."' OR `type` = '".EVENT_TYPE_START_GAME."')");
                $task_start_time = $row[0];
                $row = query_1st_row("SELECT TIME_FORMAT(TIMESTAMPADD(SECOND, '$time_sec', '{$row[1]}'), '%H:%i:%s')");
                $finish_time = $row[0];
                $start_finish = '<br><small>'.$task_start_time.'&nbsp;- '.$finish_time.'</small>';
            }

            // если задание, группа или игра ещё не началась, выводить ничего не надо
            if (!$started)
            {
                $msg = '';
            } else if (($object_classes[$id] == OBJECT_TYPE_GROUP) && $finished)
            {
                $msg = '<font color=lightgray>&radic;&nbsp;'.$clock.'<br>'.$time.$start_finish.'</font>';
            } else if ($it_is_game && $finished)
            {
                $msg = '<font color=gray>&radic;&nbsp;'.$clock.'<br>'.$time.$start_finish.'</font>';
            } else if (is_task_passed($id, $user))
            {
                $msg = '<font color=green>&radic;&nbsp;'.$clock.'<br>'.$time.$start_finish.'</font>';
            } else if ($finished)
            {
                $msg = '<font color=red>&times;&nbsp;'.$clock.'<br>'.$time.$start_finish.'</font>';
            } else if (is_task_waiting($id, $user))
            {
                $msg = '<font color=blue>...</font>';
            } else
            {
                $red_flag = TRUE;
                $msg = '<font color=blue>!&nbsp;'.$clock.'<br>'.$time.$start_finish.'</font>';
            }

            if ($red_flag && !($object_classes[$id] == OBJECT_TYPE_GROUP || $it_is_game) && ($task_types[$id] != 1))
            {
                $current_task_times["$user"] = $time;
                $red_flag = ' style="border:2px dashed orange;" ';
            } else { $red_flag = ''; }

            echo "<td $red_flag>$msg</td>\n";
        }

        echo "</tr>\n";
    }

    $list = get_childs($id);
    foreach ($list as $item)
    {
        list_tasks($item, $level + 1);
    }
}

echo "&radic; - выполнено, &times; - таймаут, ... - ожидает, ! - выполняется<br>";
if (!is_game_finished_by_all($game))
{
    $row = query_1st_row("SELECT count(`user_id`) FROM `session`");
    $sessions = $row[0];
    $row = query_1st_row("SELECT count(`ts`) FROM `mutex`");
    $locks = $row[0];
    echo "Сессий: $sessions, блокировок: $locks<br>";
}
echo "<br>";

unset($plain_task_list[0]);

if (is_game_finished_by_all($game))
{
    // игра окончена, публикуем комменты

    echo "<b>Бонусы и штрафы:</b><br><br>";

    // бонусы и штрафы
    foreach ($users_sorted as $cur_user)
    {
        echo "<i>Команда {$user_names[$cur_user]}, бонусы:</i><br>";

        $bonus_count = 0;

        $qr = query_result("SELECT `id`, `linked` FROM `game_log` WHERE `user` = '$cur_user' AND `game` = '$game' AND `type` = '".EVENT_TYPE_TASK_PASSED."'");
        while ($row = mysql_fetch_array($qr))
        {
            if (is_it_bonus($row['linked']))
            {
                $result = query_val_by_key('time_penalty', 'object_data_'.OBJECT_TYPE_TASK, 'id', $row['linked']);
                $bonus_name = getName($row['linked']);

                if ($result > 0) {    
                    $result = format_seconds($result);
                    echo "Бонус $result (\"$bonus_name\")<br>";

                    $bonus_count++;
                }
            }
        }

        $result = query_result("SELECT `id`, `param`, `info`, `user`, `type` FROM `game_log` WHERE `user` = '$cur_user' AND `game` = '$game' AND `type` = '".EVENT_TYPE_MANUAL_BONUS."'");
        while ($row = mysql_fetch_array($result))
        {
            if ($row['param'] > 0) {    
                $row['param'] = format_seconds($row['param']);
                echo "Бонус {$row['param']}, причина: \"{$row['info']}\"<br>";

                $bonus_count++;
            }
        }

        if ($bonus_count == 0) { echo "Бонусов нет.<br>"; }

        echo "<i>Команда {$user_names[$cur_user]}, штрафы:</i><br>";

        $penalty_count = 0;

        // здесь определяется, штрафовать ли при ручном сливе задания админом, или нет
        // $qr = query_result("SELECT `id`, `linked` FROM `game_log` WHERE `user` = '$cur_user' AND `game` = '$game' AND (`type` = '".EVENT_TYPE_TIME_LIM."' OR `type` = '".EVENT_TYPE_MANUAL_DECLINE."')");
        // todo: вспомнить, почему предыдущий кусок закомментирован. не от того ли, что при таком раскладе штраф назначается дважды?
        $qr = query_result("SELECT `id`, `linked` FROM `game_log` WHERE `user` = '$cur_user' AND `game` = '$game' AND (`type` = '".EVENT_TYPE_TIME_LIM."')");
        while ($row = mysql_fetch_array($qr))
        {
            if (is_it_task($row['linked']))
            {
                // если это задание
                $result = query_val_by_key('time_penalty', 'object_data_'.OBJECT_TYPE_TASK, 'id', $row['linked']);
                $task_name = getName($row['linked']);

                if ($result > 0) {    
                    $result = format_seconds($result);
                    echo "Штраф $result за слив задания \"$task_name\"<br>";

                    $penalty_count++;
                }
            }
        }

        // fixme: этот вариант не тестирован
        $qr2 = query_result("SELECT `id`, `linked` FROM `game_log` WHERE `user` = '$cur_user' AND `game` = '$game' AND `type` = '".EVENT_TYPE_HINT_RQ."'");
        while ($row2 = mysql_fetch_array($qr2))
        {
            $hint_name = getName($row['linked']);
            $task_name = getName(get_object_parent($row['linked']));
            $result = query_val_by_key('penalty', 'object_data_'.OBJECT_TYPE_HINT, 'id', $row2['linked']);

            if ($result > 0) {    
                $result = format_seconds($result);
                echo "Штраф $result за подсказку \"$hint_name\" к бонусу \"$task_name\"<br>";

                $penalty_count++;
            }
        } 

        $result = query_result("SELECT `id`, `param`, `info`, `user`, `type` FROM `game_log` WHERE `user` = '$cur_user' AND `game` = '$game' AND `type` = '".EVENT_TYPE_MANUAL_PENALTY."'");
        while ($row = mysql_fetch_array($result))
        {
            if ($row['param'] > 0) {    
                $row['param'] = format_seconds($row['param']);
                echo "Штраф {$row['param']}, причина: \"{$row['info']}\"<br>";

                $penalty_count++;
            }
        }

        if ($penalty_count == 0) { echo "Штрафов нет.<br>"; }

        echo "<br>";
    }

    echo "<b>Информация о игре:</b><br><br>";

    // комментарии
    foreach ($plain_task_list as $task)
    {
        $task_name = getName($task);

        if (is_it_group($task))
        {
            echo "<b style=color:gray>Группа $task_name</b><br>";

        } else {

            $bonus_time = '';

            if (is_it_bonus($task)) {
                $type_name = 'Бонус';
                $type = TASK_TYPE_BONUS;

                // Для бонусов сразу покажем бонусное время
                $bonus_time = query_val_by_key('time_penalty', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task);
                $bonus_time = format_seconds($bonus_time);
                $bonus_time = " ($bonus_time)";
            } else {
                $type_name = 'Задание';
                $type = TASK_TYPE_TASK;
            }

            echo "<b>$type_name \"$task_name\" $bonus_time</b><br>";

            $level_time = array();
            foreach ($users as $cur_user)
            {
                $time = time_on_task($task, '', $cur_user); $time = $time[1];
                $level_time[] = $time;
            }
            $users_sorted = $users;
            array_multisort($level_time, $users_sorted);

            echo "<table width=100%><tr><td width=300 valign=top><table>";
            $cnt = 0;
            foreach ($users_sorted as $cur_user)
            {
                $row = query_1st_row("SELECT SEC_TO_TIME({$level_time[$cnt]});");
                $time = $row[0];

                // Посчитаем время старта и финиша
                $row = query_1st_row("SELECT TIME_FORMAT(`ts`, '%H:%i:%s'), `ts` FROM `game_log` WHERE `game` = '$game' AND `user` = '$cur_user' AND `linked` = '$task' AND (`type` = '".EVENT_TYPE_START_TASK."' OR `type` = '".EVENT_TYPE_START_GAME."')");
                $task_start_time = $row[0];
                $row = query_1st_row("SELECT TIME_FORMAT(TIMESTAMPADD(SECOND, '{$level_time[$cnt]}', '{$row[1]}'), '%H:%i:%s')");
                $finish_time = $row[0];
                $start_finish = '<br><small>'.$task_start_time.'&nbsp;- '.$finish_time.'</small>';

                $time .= $start_finish;

                if (is_task_passed($task, $cur_user)) { $time = "<font color=green>$time</font>"; }
                else { $time = "<font color=red>$time</font>"; }

                echo "<tr><td>{$user_names[$cur_user]}</td><td>$time</td></tr>";

                $cnt++;
            }
            echo "</table></td><td valign=top>";

            $task_text = query_val_by_key('task_text', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task);
            echo "<i>Текст задания:</i><br>$task_text<br>";

            $hint_count = 0;
            $sql = query_result("SELECT `id` FROM `tree` WHERE `parent` = '$task' AND `class` = '".OBJECT_TYPE_HINT."' ORDER BY `order_token`");
            while ($row = mysql_fetch_array($sql))
            {
                $hint_count++;
                $hint_text = query_val_by_key('text', 'object_data_'.OBJECT_TYPE_HINT, 'id', $row['id']);
                echo "<i>Подсказка $hint_count:</i><br>$hint_text<br>";
            }

            $dc = query_val_by_key('dc', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task);
            echo "<i>Код опасности: $dc</i><br>";
            $lim = task_time_limit($task);
            if (task_time_limit_seconds($task) == 0) { $lim = 'не задан'; }
            echo "<i>Лимит времени: $lim</i><br>";

            $penalty = query_val_by_key('time_penalty', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task);
            $row = query_1st_row("SELECT SEC_TO_TIME('$penalty')");
            if ($type == TASK_TYPE_TASK)
            {
                echo "<i>Штраф за слив: $row[0]</i><br>";
            } else if ($type == TASK_TYPE_BONUS) {
                echo "<i>Бонусное время: $row[0]</i><br>";
            }

            $codes = query_val_by_key('code', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task);
            echo "<i>Код(ы): $codes</i><br>";

            echo "<br>";

            $comment = query_val_by_key('comment', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task);
            echo "<i>Комментарий:</i><br>$comment<br>";

            echo "</td></tr></table><br>";
        }
    }
}

if (!$edit_mode)
{
    // контроль времени выполнения
    if (CFG_LOG_EXECUTION_TIME == 1)
    {
        debug_msg("gameflow.php, время выполнения: ".strval(microtime_float() - $start_time)." сек.");
    }

    exit;
}

if (!$current_game_is_finished_by_all)
{
    // Ручная выдача
    echo "<b>Выдать команде</b><br>";
    echo "<form method=post><input type=hidden name=action value=manual_schedule>";
    echo "<select name=param_user>";
    foreach ($users as $user)
    {
        $name = $user_names[$user];
        echo "<option value=$user>$name";
    }
    echo "</select> <b>задание</b> <select name=param_task>";
    foreach ($plain_task_list as $task)
    {
        $name = $object_names[$task];
        echo "<option value=$task>$name";
    }
    echo "</select> при наступлении события <select name=param_type onchange=\"document.getElementById('param_linked').disabled=(this.value==0);\">";
    echo "<option value=".EVENT_TYPE_START_TASK.">получение задания";
    echo "<option value=".EVENT_TYPE_END_TASK.">завершение задания";
    echo "<option value=0>немедленно";
    echo "</select> <select name=param_linked id=param_linked>";
    foreach ($plain_task_list as $task)
    {
        $name = $object_names[$task];
        echo "<option value=$task>$name";
    }
    echo "</select><input type=submit></form>";

    // Выведем список назначенных вручную заданий
    $header = FALSE;
    $result = query_result("SELECT `id`, `user`, `task`, `linked`, `type` FROM `manual_task_list` WHERE `game` = '$game' ORDER BY `user`, `linked`");
    while ($row = mysql_fetch_array($result))
    {
        if (!$header) { echo "<b>Список назначенных вручную заданий:</b><br>"; }
        $header = TRUE;

        $user_name = $user_names[$row['user']];
        $task_name = getName($row['task']);
        $linked_name = getName($row['linked']);

        if ($row['type'] == EVENT_TYPE_START_TASK) { $type_name = 'после получения задания '; }
        if ($row['type'] == EVENT_TYPE_END_TASK) { $type_name = 'после завершения задания '; }

        echo "<a style=\"color:red\" href=?game=$game&action=kill_manual&parameter={$row['id']}>(x)</a> ";
        echo "\"$task_name\" пользователю \"$user_name\" ($type_name \"$linked_name\")<br>";
    }
    if ($header) { echo "<br>"; }

    // Ручной зачет
    echo "<b style=\"color:green\">Засчитать команде</b><br>";
    echo "<form method=post><input type=hidden name=action value=manual_accept>";
    echo "<select name=param_user>";
    foreach ($users as $user)
    {
        $name = $user_names[$user];
        echo "<option value=$user>$name";
    }
    echo "</select> <b>задание</b> <select name=param_task>";
    foreach ($plain_task_list as $task)
    {
        if (is_it_task($task))
        {
            $name = $object_names[$task];
            echo "<option value=$task>$name";
        }
    }
    echo "</select><input type=submit></form>";

    // Ручной слив
    echo "<b style=\"color:red\">Слить команде</b><br>";
    echo "<form method=post><input type=hidden name=action value=manual_decline>";
    echo "<select name=param_user>";
    foreach ($users as $user)
    {
        $name = $user_names[$user];
        echo "<option value=$user>$name";
    }
    echo "</select> <b>задание</b> <select name=param_task>";
    foreach ($plain_task_list as $task)
    {
        if (is_it_task($task))
        {
            $name = $object_names[$task];
            echo "<option value=$task>$name";
        }
    }
    echo "</select><input type=submit></form>";

    // Полное удаление задания
    echo "<b style=\"color:red\">Полностью удалить задание (НЕОБРАТИМО!) </b><br>";
    echo "<form name=full_erase method=post><input type=hidden name=action value=full_erase>";
    echo "<select name=param_task>";
    foreach ($plain_task_list as $task)
    {
        $name = $object_names[$task];
        echo "<option value=$task>$name";
    }
    echo "</select><input type=submit ";
    echo "onClick=\"var answer=window.prompt('ВНИМАНИЕ! Вы собираетесь полностью удалить задание из движка, ";
    echo "как будто его и не было. При этом удалятся также все упоминания об этом задании из лога игры. ";
    echo "Задание удалится насовсем. Отменить эту операцию нельзя, понимаете? Если не передумали, наберите ниже число 925713648');";
    echo "if (answer=='925713648'){full_erase.submit();}else{return false;}\"></form>";
}

// А вот ручные бонусы и штрафы могут быть доступны, даже если игра закончена.
// Потому что через них назначаются корректировки.

// Ручные бонусы
echo "<b>Назначить команде</b><br>";
echo "<form method=post><input type=hidden name=action value=manual_bonus>";
echo "<select name=param_user>";
foreach ($users as $user)
{
    $name = $user_names[$user];
    echo "<option value=$user>$name";
}
echo "</select> <b style=\"color:green\">бонус</b> в размере <input name=parameter> секунд ";
echo "(причина: <input name=param_info>) <input type=submit></form>";

echo "<b>Назначить команде</b><br>";
echo "<form method=post><input type=hidden name=action value=manual_penalty>";
echo "<select name=param_user>";
foreach ($users as $user)
{
    $name = $user_names[$user];
    echo "<option value=$user>$name";
}
echo "</select> <b style=\"color:red\">штраф</b> в размере <input name=parameter> секунд ";
echo "(причина: <input name=param_info>) <input type=submit></form>";

// Выведем список назначенных вручную штрафов и бонусов
$header = FALSE;
$result = query_result("SELECT `id`, `param`, `info`, `user`, `type` FROM `game_log` WHERE `game` = '$game' AND (`type` = '".EVENT_TYPE_MANUAL_BONUS."' OR `type` = '".EVENT_TYPE_MANUAL_PENALTY."')");
while ($row = mysql_fetch_array($result))
{
    if (!$header) { echo "<b>Список назначенных вручную бонусов:</b><br>"; }
    $header = TRUE;

    $user_name = $user_names[$row['user']];
    if ($row['type'] == EVENT_TYPE_MANUAL_BONUS) { $type_name = 'Бонус'; } else { $type_name = 'Штраф'; }

    echo "<a style=\"color:red\" href=?game=$game&action=kill_manual_bp&parameter={$row['id']}>(x)</a> ";
    $formatted_seconds = format_seconds($row['param']);
    echo "$type_name $formatted_seconds ({$row['param']} сек.) пользователю \"$user_name\", причина: \"{$row['info']}\"<br>";
}
if ($header) { echo "<br>"; }

/*
if (!$current_game_is_finished_by_all)
{
    echo "<b>Добавить<sup style=font-size:7pt>1</sup> команде</b><br>";
    echo "<form method=post><input type=hidden name=action value=manual_prolongate>";
    echo "<select name=param_user>";
    foreach ($users as $user)
    {
        $name = $user_names[$user];
        echo "<option value=$user>$name";
    }
    echo "</select><input size=8 value=300 name=parameter> секунд на прохождение <select name=param_task>";
    foreach ($plain_task_list as $task)
    {
        $name = $object_names[$task];
        echo "<option value=$task>$name";
    }
    echo "</select><input type=submit></form>";
    echo "<sup style=font-size:7pt>1</sup> При этом время начала задания будет сдвинуто вперед на указанное число секунд, ";
    echo "и в лог запишется информационное сообщение. Эта функция полезна в случае ";
    echo "форсмажорных обстоятельств на уровне, когда время близко к сливу.<br><br>";

    echo "<a href=?game=$game&action=stop_game>Завершить игру</a><br>";
}
*/

// контроль времени выполнения
if (CFG_LOG_EXECUTION_TIME == 1)
{
    debug_msg("gameflow.php, время выполнения: ".strval(microtime_float() - $start_time)." сек.");
}
