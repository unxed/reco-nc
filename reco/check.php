<?php

include('../libs/globaltree/auth.php');
include('playhelp.php');

// контроль времени выполнения
$start_time = microtime_float();

header("Content-Type: text/html; charset=utf-8");
query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

// аутентификация
checkAuth('../admin/php/login.php?back=../../reco/index.php');
$user = $_SESSION['user_id'];

secureGetRequestData('game', 'action', 'parameter', 'param_user', 'param_task', 'param_linked', 'param_info', 'param_type');

// дизайн
echo '<link rel="stylesheet" href="css/tech.css" type="text/css">';

// Проверка безопасности
if (($game == '') || !is_it_game($game))
{
    die;
}

// Проверка безопасности
$owner = query_val_by_key('owner', 'tree', 'id', $game);
if (($owner != $user) && !isAdmin($user))
{
    die('error');
}

define("ERROR_TYPE_ERROR",          1);
define("ERROR_TYPE_WARNING",        2);
define("ERROR_TYPE_NOTE",           3);

$game_name = getName($game);
echo "<h2>Проверка структуры игры $game_name</h2>";

$error_count = 0;
$warning_count = 0;
check_object($game);

echo "<br>";
if ($error_count > 0) { echo "Ошибок: $error_count, предупреждений: $warning_count.<br>"; }
else if ($warning_count > 0) { echo "Ошибок: 0, предупреждений: $warning_count.<br>"; }
else { echo "0 ошибок, 0 предупреждений.<br>"; }

function check_object($id)
{
    $type = get_object_type($id);
    $name = getName($id);

    switch ($type)
    {
        case OBJECT_TYPE_GAME:

            $start_time = query_val_by_key('start_time', 'object_data_'.OBJECT_TYPE_GAME, 'id', $id);
            if ($start_time == '') { echo "Время старта игры не задано<br>"; $errors_count++; }

            $row = query_1st_row("SELECT IF(NOW() > STR_TO_DATE('$start_time', '%d.%m.%Y %H:%i'), '1', '0')");
            if ($row[0] == 1)
            {
                show_error("Время старта игры - в прошлом", ERROR_TYPE_ERROR);
            }

            $finished = query_val_by_key('finished', 'object_data_'.OBJECT_TYPE_GAME, 'id', $id);
            if ($finished == 1)
            {
                show_error("Игра помечена как завершенная.", ERROR_TYPE_ERROR);
            }

            $announce = query_val_by_key('announce', 'object_data_'.OBJECT_TYPE_GAME, 'id', $id);
            if ($finished == '')
            {
                show_error("Не задан анонс игры.", ERROR_TYPE_ERROR);
            }

        break;

        case OBJECT_TYPE_GROUP:

            $limit = query_val_by_key('time_limit', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id);
            $parent_limit = query_val_by_key('time_limit_parent', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id);

            if (($limit == '') && ($parent_limit == '') && is_it_bonus($id))
            {
                $error = "Для бонус-группы \"$name\" не задано время завершения, ";
                $error .= "и не задано время завершения после Завершения Родительской Группы. ";
                $error .= "Эта группа будет выполняться, пока не сработает таймаут ";
                $error .= "у одной из родительских групп (если он задан).";
                show_error($error, ERROR_TYPE_WARNING);
            }

            $show_comment = query_val_by_key('show_comment', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id);
            $comment = query_val_by_key('comment', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id);

            if (is_content_empty($comment) && $show_comment == '1')
            {
                $error = "Для группы \"$name\" указана опция отображения комментария, а комментарий не задан.";
                show_error($error, ERROR_TYPE_WARNING);
            }

            $task_type = query_val_by_key('task_type', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id);
            $prio_max = query_val_by_key('prio_max', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id);
            $prio_mid = query_val_by_key('prio_mid', 'object_data_'.OBJECT_TYPE_GROUP, 'id', $id);
            if (($task_type == GROUP_TYPE_RANDOM_PRIO) && (intval($prio_max) == 0) && (intval($prio_mid) == 0))
            {
                $error = "Для группы \"$name\" указан режим случайной выдачи с приоритетами, ";
                $error .= "а количество приоритетных заданий не задано. Будет работать обычная случайная выдача.";
                show_error($error, ERROR_TYPE_WARNING);
            }

            if (count(get_childs($id)) == 0)
            {
                $error = "В группе \"$name\" нет объектов.";
                show_error($error, ERROR_TYPE_ERROR);
            }

        break;

        case OBJECT_TYPE_TASK:

            $is_bonus = is_it_bonus($id);

            $limit = query_val_by_key('time_limit', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id);
            $parent_limit = query_val_by_key('time_limit_parent', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id);

            if (($limit == '') && ($parent_limit == '') && $is_bonus)
            {
                $error = "Для бонуса \"$name\" не задано время завершения, ";
                $error .= "и не задано время завершения после Завершения Родительской Группы. ";
                $error .= "Этот бонус будет выполняться, пока не сработает таймаут ";
                $error .= "у одной из родительских групп (если он задан).";
                show_error($error, ERROR_TYPE_WARNING);
            }

            if ((!$is_bonus) && ($limit == ''))
            {
                $error = "Для задания \"$name\" не задан лимит времени выполнения.";
                show_error($error, ERROR_TYPE_WARNING);
            }

            if ((!$is_bonus) && ($limit == '0'))
            {
                $error = "Для задания \"$name\" задан нулевой лимит времени выполнения. ";
                $error .= "Оно будет слито сразу после выдачи.";
                show_error($error, ERROR_TYPE_ERROR);
            }

            $task_text = query_val_by_key('task_text', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id);
            if (is_content_empty($task_text))
            {
                $error = "Для задания \"$name\" не задан текст задания.";
                show_error($error, ERROR_TYPE_ERROR);
            }

            $code = query_val_by_key('code', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id);
            if ($code == '')
            {
                $error = "Для задания \"$name\" не задан код.";
                show_error($error, ERROR_TYPE_ERROR);
            }

            $time_penalty = query_val_by_key('time_penalty', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id);
            if ((!$is_bonus) && (intval($time_penalty) == 0))
            {
                $error = "Для задания \"$name\" не задан штраф за слив.";
                show_error($error, ERROR_TYPE_WARNING);
            }

            $time_penalty = query_val_by_key('time_penalty', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id);
            if (($is_bonus) && (intval($time_penalty) == 0))
            {
                $error = "Для бонуса \"$name\" не задано бонусное время.";
                show_error($error, ERROR_TYPE_WARNING);
            }

            $dc = query_val_by_key('dc', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id);
            if ($dc == '')
            {
                $error = "Для задания \"$name\" не задан код опасности.";
                show_error($error, ERROR_TYPE_WARNING);
            }

            $comment = query_val_by_key('comment', 'object_data_'.OBJECT_TYPE_TASK, 'id', $id);
            if (is_content_empty($comment))
            {
                $error = "Для задания \"$name\" не задан комментарий.";
                show_error($error, ERROR_TYPE_NOTE);
            }

            if (!$is_bonus && (count(get_childs($id)) == 0))
            {
                $error = "Для задания \"$name\" не заданы подсказки.";
                show_error($error, ERROR_TYPE_WARNING);
            }

        break;

        case OBJECT_TYPE_HINT:

            $delay = query_val_by_key('delay', 'object_data_'.OBJECT_TYPE_HINT, 'id', $id);
            $penalty = query_val_by_key('penalty', 'object_data_'.OBJECT_TYPE_HINT, 'id', $id);
            $parentName = getName(get_object_parent($id));

            if (($delay == '') && ($penalty == ''))
            {
                $error = "Время выдачи подсказки \"$name\" к заданию \"$parentName\" не задано, ";
                $error .= "также не задан штраф за ручной запрос подсказки. Нужно указать либо одно, либо другое.";
                show_error($error, ERROR_TYPE_ERROR);
            }

            $text = query_val_by_key('text', 'object_data_'.OBJECT_TYPE_HINT, 'id', $id);
            if (is_content_empty($text))
            {
                show_error("Текст подсказки \"$name\" к заданию \"$parentName\" не задан.", ERROR_TYPE_ERROR);
            }

        break;
    }

    // проверим вложенные объекты, если они есть
    $childs = get_childs($id);
    foreach ($childs as $item)
    {
        check_object($item);
    }
}

function show_error($msg, $type)
{
    GLOBAL $error_count, $warning_count;

    if ($type == ERROR_TYPE_ERROR)
    {
        $error_count++;

        echo "<font color=red><b>$msg</b></font><br>";

    } else if ($type == ERROR_TYPE_WARNING) {

        $warning_count++;

        echo "<font color=DarkOrange>$msg</font><br>";

    } else if ($type == ERROR_TYPE_NOTE) {

        echo "<font color=#ccc>$msg</font><br>";
    }
}
