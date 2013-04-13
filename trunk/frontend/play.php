<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../libs/globaltree/');
include('helpers.php');
include('playhelp.php');

initAuth();

// контроль времени выполнения
if (CFG_LOG_EXECUTION_TIME == 1) { $start_time = microtime_float(); }

header("Content-Type: text/html; charset=utf-8");
query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

// аутентификация

checkAuth('../admin/php/login.php?back=../../frontend/index.php');
$user = $_SESSION['user_id'];

secureGetRequestData('game_id', 'action', 'code', 'task', 'hint', 'selection');
convert_to_int('game_id', 'task', 'hint', 'selection');

// Проверка безопасности
// Если нам не указали id игры, завершим работу
if (!is_it_game($game_id)) { exit_with_redirect('index.php'); }

// Не пора ли начинать указанную игру?
check_start_game($game_id, $user);

// Проверка безопасности
// Если игра ещё не началась, завершим работу
if (!is_game_started($game_id, $user)) { exit_with_redirect('index.php'); }

// Если игра для пользователя началась,
// мы можем вывести его завершенные задания и бонусы
// Завершенные задания и бонусы
if ($action == 'finished')
{
    // fixme: копипаст блока дизайна
    
    // дизайн
    echo '<link rel="stylesheet" href="css/default.css" type="text/css">';

    // пользовательский дизайн
    if (!isAdmin($user) && CFG_ALLOW_ADDITIONAL_DESIGN == 1)
    {
        $template = 'templates/play.tpl';
        if (file_exists($template)) { readfile($template); }
    }

    echo "<h2>Завершенне задания и бонусы (<a href=play.php?game_id=$game_id>назад</a>)</h2>";

    $sql = query_result("SELECT `linked` FROM `game_log` WHERE `game` = '$game_id' AND `user` = '$user' AND `type` = '".EVENT_TYPE_END_TASK."' ORDER BY `ts`");
    while ($row = mysql_fetch_array($sql))
    {
        if (get_object_type($row['linked']) == OBJECT_TYPE_TASK)
        {
            $name = getName($row['linked']);
            if (is_it_bonus($row['linked'])) { $type_name = 'Бонус'; } else { $type_name = 'Задание'; }

            $parent_name = getName(get_object_parent($row['linked']));

            echo "<hr><b>$type_name \"$name\" из группы \"$parent_name\"</b><br>";

            $text = query_val_by_key('task_text', 'object_data_'.OBJECT_TYPE_TASK, 'id', $row['linked']);
            $code = query_val_by_key('code', 'object_data_'.OBJECT_TYPE_TASK, 'id', $row['linked']);

            echo "$text<br>";

            if (is_task_passed($row['linked'], $user))
            {
                echo "<i><font color=green>Задание выполнено, код(ы): $code</font></i><br>";
            } else {
                echo "<i><font color=red>Задание слито</font></i><br>";
            }
        }
    }
    echo "<hr>";

    exit;    
}

// Если игра по логам закончилась, просто вернем пользователя к списку игр
//
if (is_game_finished_in_log($user, $game_id))
{
    exit_with_redirect('index.php');
}

// Проверим, не закончилась ли игра.
//
// Заодно is_game_finished выполнит для нас следующие действия:
// check_active_tasks_timeout - проверит, не вышло ли время на одном из заданий
// schedule_next_tasks_top - выдаст невыданные задания
// check_waiting_tasks - убедится, что выданы "ожидающие" задания
//
// Так как эти действия необходимо выполнить прежде, чем проверять,
// не закончилась ли игра, их всё равно нужно делать в is_game_finished,
// так что не будем здесь делать всё это ещё раз.
//

// Сольем слитые задания, выдадим невыданные, проверим, не закончилась ли игра
$game_finished = is_game_finished($user, $game_id);

// Выдадим подсказки, если требуется
schedule_hints($game_id, $user);

if ($game_finished)
{
    exit_with_redirect('index.php');
}

// Выведем сообщение, если нас об этом просили
$query = "SELECT `id`, `text`, `color` FROM `messages` WHERE `user` = '$user';";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
while ($row = mysql_fetch_array($sql))
{
    echo "Сообщение системы: <span style=color:{$row['color']};>{$row['text']}</span><br><br>";
    message_shown($row['id']);

    // Если мы попали сюда, то в результате редиректа с целью обновления страницы
    // после выполнения какого-то действия. Соответственно, действие уже выполнено
    // (хоть в POST и сохраняется указание на его выполнение).
    // Такая история может быть, к примеру, если редирект идет с кодом 307
    $action = '';
}

// ---------- Игра идёт, все операции обслуживания сделаны

// Выполним требуемые действия

// Проверка введенного кода
if ($action == 'code')
{
    // Проверка безопасности
    if (!is_it_task($task) && !is_it_bonus($task)) { die('error'); }

    // Проверка безопасности - 2
    // Если есть доступные запросы на выбор ветки игры, коды принимать нельзя
    $selection_queries_available = FALSE;
    $sql = query_result("SELECT `id`, `group` FROM `task_selection_request` WHERE `user` = '$user'");
    while ($row = mysql_fetch_array($sql))
    {
        $selection_queries_available = $selection_queries_available || (get_game_by_object_id($row['group']) == $game_id);
    }
    if ($selection_queries_available) { $code = ''; }

    // Если время на задании вышло, выведем сообщение об этом
    // Проверка безопасности - 3
    // Если задание ещё не выдано, уже пройдено или слито администратором, пользователь также получит ошибку
    if (!(is_task_active($task, $user)))
    {
        $msg = 'Время на задание истекло или задание завершено администратором.';
        send_message($msg, 'orange', "?game_id=$game_id");
    }

    // Проверим код
    $code_check_result = check_code($task, $code, TRUE, TRUE, $user, $game_id);
    if ($code_check_result == 1)
    {
        $msg = 'Код верен!';
        send_message($msg, 'green', "?game_id=$game_id");
    } else
    {
        // Проверим варианты, почему код не принят,
        // и выведем соответствующее сообщение

        if ($code_check_result == -1)
        {
            $msg = 'Введен СЛИВ-код, задание слито.';
            send_message($msg, 'red', "?game_id=$game_id");
        }

        if (!check_code_length($task, $code))
        {
            if (CFG_WARN_WRONG_LENGTH == 1)
            {
                $msg = 'На этом задании нет кода такой длинны.';
                send_message($msg, 'red', "?game_id=$game_id");
            }
        }

        if (code_already_entered($code, $game_id, $user, $task))
        {
            $msg = 'Такой код уже вводился и засчитан.';
            send_message($msg, 'red', "?game_id=$game_id");
        }

        if (code_similarity($task, $code) <= CFG_CODE_SIMILARITY_THRESHOLD)
        {
            $msg = 'Уточните код.';
            send_message($msg, 'red', "?game_id=$game_id");
        }

        $msg = 'Код неверен.';
        send_message($msg, 'red', "?game_id=$game_id");
    }
}

// Выбор задания/группы пользователем
if ($action == 'task_selection')
{
    // Проверять, от этой ли игры запрос, здесь смысла нет:
    // через интерфейс человек не сможет выбрать ветку игры, отличающейся от текущей,
    // а если будет напряму слать запрос - пускай выбирает, если он формально имеет
    // право выбора ветки в другой игре.

    $query = "SELECT `id`, `group` FROM `task_selection_request` WHERE `user` = '$user'";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    while ($row = mysql_fetch_array($sql))
    {
        $group_id = $row['group'];

        $list = get_childs($group_id);
        foreach ($list as $item)
        {
            if (($item == $selection) && !is_task_active($item, $user) && !is_task_finished($item, $user))
            {
                write_log(EVENT_TYPE_TASK_SELECTION, $selection, '', '', $game_id, $user);

                $query3 = "DELETE FROM `task_selection_request` WHERE `id` = '{$row['id']}'";
                $sql3 = mysql_query($query3) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);

                schedule_next_tasks($selection, '', $user);

                send_message('Выбор принят', 'blue', "?game_id=$game_id");

            } else if (($item == $selection) && (is_task_finished($item, $user))) {

                send_message('Эта часть игры уже пройдена!', 'blue', "?game_id=$game_id");
            }            
        }
    }
}

// Выдадим подсказку по запросу, если нас об этом просили
if ($action == 'hint_rq')
{
    // Проверка безопасности
    $delay = intval(get_hint_delay($hint));
    $parent_is_active = is_task_active(query_val_by_key('parent', 'tree', 'id', $hint), $user);
    if ((get_object_type($hint) == OBJECT_TYPE_HINT) && ($delay == 0) && !is_hint_scheduled($hint, $user) && $parent_is_active)
    {
        request_hint($hint, $game_id, $user);
        send_message('Подсказка выдана', 'blue', "?game_id=$game_id");
    } else {
        die('error');
    }
}

// Досюда мы старались ничего напрямую не выводить в браузер,
// чтобы сохранить возможность сделать редирект после send_message
// заголовком, а не через meta refresh. Дальше - можно,
// поэтому вывод заголовка аутентификации делаем здесь.

$welcome = query_val_by_key('login', 'user', 'id', $_SESSION['user_id']);
echo "Привет, $welcome! <a href=../admin/php/login.php?logout&back=../../frontend/index.php>Выход</a><br>";

// дизайн
echo '<link rel="stylesheet" href="css/default.css" type="text/css">';

// пользовательский дизайн
if (!isAdmin($user) && CFG_ALLOW_ADDITIONAL_DESIGN == 1)
{
    $template = 'templates/play.tpl';
    if (file_exists($template)) { readfile($template); }
}

// Выведем блок автообновления
auto_refresh();

// Вывод названия игры
$game_name = query_val_by_key('name', 'tree', 'id', $game_id);
echo "<b>Игра $game_name</b> (<a href=index.php>список игр</a>, <a href=?action=finished&game_id=$game_id>завершенные задания</a>)<br>";

// Выведем комментарии к группам, если такие имеются
// fixme: медленный запрос
$sql = query_result("SELECT `linked` FROM `game_log` WHERE `user` = '$user' AND `game` = '$game_id' AND `type` = '".EVENT_TYPE_START_TASK."' AND `linked` IN (SELECT `id` FROM `tree` WHERE `type` = '".OBJECT_TYPE_GROUP."')");
while ($row = mysql_fetch_array($sql))
{
    if (!is_task_finished($row['linked'], $user))
    {
        $group_name = getName($row['linked']);
        $comment = query_val_by_key('comment', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $row['linked']);
        $show_comment = query_val_by_key('show_comment', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $row['linked']);

        if (!is_content_empty($comment) && ($show_comment == 1))
        {
            // echo "<b>$group_name: $comment</b>";
            echo "<b>$comment</b>";
        }
    }
}

// Выведем запросы на выбор задания, если они есть
$selection_queries_available = FALSE;
$sql = query_result("SELECT `id`, `group`, TIMESTAMPDIFF(SECOND, `ts`, NOW()) FROM `task_selection_request` WHERE `user` = '$user'");
while ($row = mysql_fetch_array($sql))
{
    $group_id = $row['group'];
    if (get_game_by_object_id($group_id) == $game_id)
    {
        $selection_timeout = CFG_TASK_SELECTION_TIMEOUT;
        if ((CFG_SPEED_UP_GAME > 0) && ($selection_timeout > 0))
        {
            $selection_timeout = round($selection_timeout / CFG_SPEED_UP_GAME);
        }

        if (($selection_timeout > 0) && ($row[2] > $selection_timeout))
        {
            $list = get_childs($group_id);
            foreach ($list as $item)
            {
                if (!is_task_active($item, $user) && !is_task_finished($item, $user))
                {
                    write_log(EVENT_TYPE_TASK_SELECTION, $item, '', '', $game_id, $user);

                    $query3 = "DELETE FROM `task_selection_request` WHERE `id` = '{$row['id']}'";
                    $sql3 = mysql_query($query3) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);

                    schedule_next_tasks($item, '', $user);

                    send_message('Время истекло, назначено первое невыполненное задание', 'red', "?game_id=$game_id");
                }
            }
        } else {

            $time_left = $selection_timeout - $row[2];

            // hackfix for bucky
            if ($group_id == 1266)
            {
                echo '<p><map id="imgmap201134223445" name="imgmap201134223445">';
                if (!is_task_finished(1271, $user))
                    echo '<area shape="circle" coords="202,135,28" alt="green"  href="?action=task_selection&selection=1271&game_id=1262"></area>';
                if (!is_task_finished(1268, $user))
                    echo '<area shape="circle" coords="392,135,40" alt="red"    href="?action=task_selection&selection=1268&game_id=1262"></area>';
                if (!is_task_finished(1269, $user))
                    echo '<area shape="circle" coords="174,250,48" alt="blue"   href="?action=task_selection&selection=1269&game_id=1262"></area>';
                if (!is_task_finished(1270, $user))
                    echo '<area shape="circle" coords="350,284,90" alt="yellow" href="?action=task_selection&selection=1270&game_id=1262"></area>';
                echo '</map><img usemap="#imgmap201134223445" src="http://buckyohare.ru/planets.jpg"></p>';

                echo "<br>Время на выбор: " . $time_left . " секунд(ы)<br>";
                echo "Через это время будет автоматически назначена первая непройденная планета<br>";
                echo "<meta http-equiv=refresh content=$time_left;url=?game_id=$game_id&left=$time_left>";

                exit;
            }
            // hackfix for bucky end

            $selection_queries_available = TRUE;

            echo "<div style=\"border:1px solid red; padding: 10px;\">";
            echo "<b>Выберите задание (или группу заданий):</b><br>";

            $comment = query_val_by_key('comment', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $row['group']);
            if (!is_content_empty($comment)) { echo "$comment<br>"; }

            $silent = strpos($comment, 'noselect'); // hackfix for bucky

            if (!$silent)
            {
                echo "<form method=post>";
                echo "<input type=hidden name=game_id value=$game_id>";
                echo "<input type=hidden name=action value=task_selection>";
                echo "<select name=selection>";
            }

            $list = get_childs($group_id);
            foreach ($list as $item)
            {
                if (!(is_task_active($item, $user)) && !(is_task_finished($item, $user)))
                {
                    $no_tasks = FALSE;

                    $obj_name = obj_name($item);
                    if (!$silent) { echo "<option value=$item>$obj_name"; }
                }
            }

            if (!$silent)
            {
                echo "</select>";
                echo "<input value=Выбрать type=submit></form>";
            }

            echo "<br>Время на выбор: " . $time_left . " секунд(ы)";

            echo "</div><br>";
        }
    }
}

if ($selection_queries_available) { exit; }

// Выведем активные задания с подсказками
$no_tasks = TRUE;
$tasks = list_active_tasks(true, '', $game_id, $user);

foreach ($tasks as $task)
{
    // Получим параметры задания
    $task_text = query_val_by_key('task_text', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task);
    $time = time_on_task($task, '', $user); $time = $time[0];
    $codes_left = codes_left_on_task($task, $user);
    $codes_total = codes_total_on_task($task);

    // Это задание или бонус?
    if (is_it_bonus($task)) {
        $type_name = 'Бонус';
        $type = TASK_TYPE_BONUS;

        echo "<div style=\"border:1px solid red; padding: 10px; color: #aaa;\">";
    } else {
        $type_name = 'Задание';
        $type = TASK_TYPE_TASK;

        echo "<div style=\"border:1px solid red; padding: 10px;\">";
    }

    // Задание ожидается? Выведем время ожидания
    if (is_task_waiting($task, $user))
    {
        $waiting_time = get_task_waiting_time($task, '', $user);
        $waiting_time = $waiting_time[0];

        echo "<b>$type_name ожидается<br>";
        echo "<b>Осталось ждать: $waiting_time</b><br>";
    } else {

        // Задание активно

        $task_path = get_task_path($task, $user);

        echo "<b>$type_name [$task_path]:</b><br> $task_text<br>";

        // Проверим каждую из подсказок, связанных с этим заданием - не выдана ли она?
        $bonus_hint_request_shown = FALSE;
        $query = "SELECT `id`, `name` FROM `tree` WHERE `parent` = '$task' ORDER BY order_token;";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        while ($row = mysql_fetch_array($sql))
        {
            // Если подсказка уже выдана или запрошена пользователем
            if (is_hint_scheduled($row['id'], $user))
            {
                // Выведем её

                $hint_text = query_val_by_key('text', 'object_data_'.OBJECT_TYPE_HINT, 'id', $row['id']);
                echo "<br><b>{$row['name']}:</b>$hint_text<br>";
            } else if ($type == TASK_TYPE_BONUS)
            {
                // Если же не выдана, а тип задания - бонус,
                // выведем форму запроса подсказки, но только для первой из невыданных

                if (!$bonus_hint_request_shown)
                {
                    $hint_penalty = query_val_by_key('penalty', 'object_data_'.OBJECT_TYPE_HINT, 'id', $row['id']);

                    echo "Доступна <a href=?action=hint_rq&hint={$row['id']}>подсказка по запросу</a> ";
                    echo "(штраф за подсказку: $hint_penalty сек.).<br>";

                    $bonus_hint_request_shown = TRUE;
                }
            }
        }

        echo "<br>";

        // Общая информация по заданию и форма ввода кода

        $dc = query_val_by_key('dc', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task);
        echo "<i>Код опасности: $dc</i><br>";
        echo "<i>Время на задании: ";
        time_on_task_js($task, $user);
        echo "</i><br>";
        $lim = task_time_limit($task);
        if (task_time_limit_seconds($task) > 0)
        {
            echo "<i>Лимит времени: $lim</i><br>";
        }

        if (time_before_hint($task, $user) != '')
        {
            echo "<i>До ближайшей подсказки осталось: ";
        } else {
            echo "<i>Подсказок больше не будет.";
        }
        time_before_hint_js($task, $user);
        echo "</i><br>";

        $penalty = query_val_by_key('time_penalty', 'object_data_'.OBJECT_TYPE_TASK, 'id', $task);
        $row = query_1st_row("SELECT SEC_TO_TIME('$penalty')");
        if ($type == TASK_TYPE_TASK)
        {
            echo "<i>Штраф за слив: $row[0]</i><br>";
        } else if ($type == TASK_TYPE_BONUS) {
            echo "<i>Бонусное время: $row[0]</i><br>";
        }

        echo "<i>Требуется кодов: $codes_left (из $codes_total)</i><br>";

        // Если код составной, покажем уже принятые коды
        if (($codes_total > 1) && ($codes_left < $codes_total))
        {
            echo "<i>Принятые коды: ";
            $first = TRUE;           
            $sql = query_result("SELECT `info` FROM `game_log` WHERE `user` = '$user' AND `game` = '$game_id' AND `linked` = '$task' AND `type` = '".EVENT_TYPE_RIGHT_CODE."'");
            while ($row = mysql_fetch_array($sql))
            {
                if (!$first) { echo ', '; } else { $first = FALSE; }
                echo "<b>{$row['info']}</b>";
            }

            echo "</i><br>";
        }

        // получим последний неверный код, если такой есть, и выведем его
        $sql = query_result("SELECT `info` FROM `game_log` WHERE `linked` = '$task' AND `type` = '".EVENT_TYPE_WRONG_CODE."' AND `game` = '$game_id' AND `user` = '$user' ORDER BY `ts` DESC LIMIT 0, 1");
        $row = mysql_fetch_array($sql);
        $old_wrong_code = normalize_code($row['info']);

        echo "Код: <form method=post onsubmit=\"resumeRefresh(); return true;\">\n";
        echo "<input type=hidden name=action value=code>";
        echo "<input type=hidden name=game_id value=$game_id>";
        echo "<input type=hidden name=task value=$task>";
        echo "<input id=task_$task name=code value=\"$old_wrong_code\" size=20 maxlength=40 onkeydown=noRefresh();>&nbsp;<input value=Отправить type=submit></form>";
    }

    echo "</div><br>";

    if ($no_tasks) {
        echo "<script>document.getElementById('task_$task').focus();</script>";
    }

    $no_tasks = FALSE;
}

if ($no_tasks)
{
    echo "Активных заданий нет. Возможно, вам следует подождать назначения задания организатором игры. ";
    echo "Время ожидания не влияет на итоговую статистику.<br><br>";
}

echo "<iframe width=1 height=1 src=online.php style=\"border:0;padding:0;margin:0;position:relative;left:-20px;\"></iframe>";

// контроль времени выполнения
if (CFG_LOG_EXECUTION_TIME == 1)
{
    debug_msg("play.php, время выполнения: ".strval(microtime_float() - $start_time)." сек.");
}
