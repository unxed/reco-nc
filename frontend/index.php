<?php

//header('HTTP/1.1 503 Service Unavailable');
//exit;

set_include_path(get_include_path() . PATH_SEPARATOR . '../libs/globaltree/');
include('helpers.php');
include('playhelp.php');

initAuth();

header("Content-Type: text/html; charset=utf-8");
query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

// аутентификация
authHeader('../../frontend/index.php');
$user = $_SESSION['user_id'];

// дизайн
echo '<link rel="stylesheet" href="css/default.css" type="text/css">';

// пользовательский дизайн
if (!isAdmin($user) && CFG_ALLOW_ADDITIONAL_DESIGN == 1)
{
    $template = 'templates/index.tpl';
    if (file_exists($template)) { readfile($template); }
}

// получим параметры
secureGetRequestData('action', 'parameter');
convert_to_int('parameter');

auto_refresh();

// определим логин текущего юзера
$welcome = query_val_by_key('login', 'user', 'id', $_SESSION['user_id']);

// выполним требуемые действия
if ($action == 'send_request')
{
    if (!is_it_game($parameter)) { die('error'); }

    $finished = is_game_finished_by_all($parameter);
    if ($finished)
    {
        echo "Нельзя подать заявку на завершенную игру.";
        echo "<meta http-equiv=refresh content=5;url=?>";
        exit;
    }

    $owner = query_val_by_key('owner', 'tree', 'id', $parameter);
    if ($owner == $user)
    {
        echo "Нельзя подать заявку на собственную игру.";
        echo "<meta http-equiv=refresh content=5;url=?>";
        exit;
    }

    $login = query_val_by_key('login', 'user', 'id', $user);
    if ($login == 'stats')
    {
        echo "Нельзя подать заявку в режиме просмотра статистики. Зайдите под своим именем.";
        echo "<meta http-equiv=refresh content=5;url=?>";
        exit;
    }

    $no_requests = query_val_by_key('no_requests', 'object_data_'.OBJECT_TYPE_GAME, 'id', $parameter);
    if ($no_requests == 1)
    {
        echo "Приём заявок на эту игру временно приостановлен.";
        echo "<meta http-equiv=refresh content=5;url=?>";
        exit;
    }

    query_result("DELETE FROM `game_request` WHERE `user` = '$user' AND `game` = '$parameter'");

    $login = query_val_by_key('login', 'user', 'id', $user);
    query_result("INSERT INTO `game_request` (`id`, `user`, `game`, `approved`, `login`) VALUES (0, '$user', '$parameter', 1, '$login');");

    echo "<meta http-equiv=refresh content=0;url=?>";
    exit;
}

if ($action == 'cancel_request')
{
    if (!is_it_game($parameter)) { die('error'); }

    query_result("DELETE FROM `game_request` WHERE `user` = '$user' AND `game` = '$parameter'");

    echo "<meta http-equiv=refresh content=0;url=?>";
    exit;
}

if ($action == 'announce')
{
    if (!is_it_game($parameter)) { die('error'); }

    $announce = query_val_by_key('announce', 'object_data_'.OBJECT_TYPE_GAME, 'id', $parameter);

    $game_name = getName($parameter);

    echo "<h1>$game_name</h1>$announce";

    echo "<br><br><a href=index.php>Назад...</a>";

    exit;
}

// не пора ли начать какую-нибудь игру для данного пользователя?
check_start_game('', $user);

$login = query_val_by_key('login', 'user', 'id', $user);
if ($login == 'stats')
{
    echo "<font color=red>Вы вошли под именем <b>stats</b>. В этом режиме нельзя подавать заявки на игры, <br>";
    echo "доступен только просмотр статистики. Чтобы подать заявку и начать игру, <br>";
    echo "нажмите \"Выход\" вверху экрана и войдите под своим именем.</font><br>";
}

$header = TRUE;
$finished_games_count = 0;
$sql = query_result('SELECT `id`, `name` FROM `tree` WHERE `class` = '.OBJECT_TYPE_GAME);
while ($row = mysql_fetch_array($sql))
{
    $finished = query_val_by_key('finished', 'object_data_'.OBJECT_TYPE_GAME, 'id', $row['id']);
    $owner = query_val_by_key('owner', 'tree', 'id', $row['id']);

    if (($finished != 1) && ($login != 'stats'))
    {
        if ($header) { echo "<b>Активные игры:</b><br>"; $header = FALSE; }

        echo "{$row['name']} ";

        // нельзя подавать заявку на собственную игру, а также если ты - агент
        if (($owner != $user) && ($welcome != 'agent'))
        {
            $sql2 = query_result("SELECT `id` FROM `game_request` WHERE `user` = '$user' AND `game` = '{$row['id']}'");
            $row2 = mysql_fetch_array($sql2);
            if ($row2['id'] == '')
            {
                echo "<a href=?action=send_request&parameter={$row['id']} style=\"color:green;\">отправить заявку</a>";
            } else {
                if (!is_game_started($row['id'], $user))
                {
                    echo "<a href=?action=cancel_request&parameter={$row['id']} style=\"color:red;\">отменить заявку</a>";
                }
            }
        }

        if (is_game_started($row['id'], $user))
        {
            if (is_game_finished_in_log($user, $row['id']))
            {
                echo " | эту игру вы уже завершили ";
                echo "(<a href=play.php?action=finished&game_id={$row['id']}>завершенные задания</a>)";

                $finished_games_count++;

            } else {
                echo " | <a href=play.php?game_id={$row['id']}>играть</a>";
            }
        } else {
            if ($owner == $user)
            {
                echo " (это ваша игра)";
            } else
            {
                $sql2 = query_result("SELECT DATEDIFF(STR_TO_DATE(`start_time`, '%d.%m.%Y %H:%i'), NOW()), TIMEDIFF(STR_TO_DATE(`start_time`, '%d.%m.%Y %H:%i'), NOW()) FROM `object_data_".OBJECT_TYPE_GAME."` WHERE `id` = '{$row['id']}'");
                $row2 = mysql_fetch_array($sql2);

                if (substr($row2[1], 0, 1) == '-')
                {
                    echo " (игра идет)";
                } else {
                    $left = $row2[1]; if ($row2[0] > 0) { $left = $row2[0] . ' дней'; }
                    echo " (до игры осталось: $left)";
                }
            }
        }

        if (($owner == $user) || isAdmin($user))
        {
            echo " | <a href=gameflow.php?game={$row['id']}>управление игрой</a>, <a href=check.php?game={$row['id']}>проверить структуру</a>";
        }

        if (!is_game_finished_in_log($user, $row['id']))
        {
            echo " | <a href=?action=announce&parameter={$row['id']}>анонс</a>";
        }

        echo "<br>";
    }
}
if ($finished_games_count > 0) {
    echo "Статистика по завершенным играм будет опубликована после завершения игр всеми командами.<br>";
}
echo "<br>";

// fixme: hackfix for bucky: игры от команд временно запрещены из соображений безопасности
if (isAdmin($user))
{
    // Агентам в "админке" делать нечего. Технически им туда можно зайти, но зачем провоцировать случайные ошибки?
    if ($welcome != 'agent')
    {
        echo "<a href=../admin/>Интерфейс администратора</a><br>";
    }
}

if (isAdmin($user) || CFG_ALLOW_LOG_FOR_USERS == 1)
{
    echo "<a href=log.php>Протокол игр</a><br><br>";
}

$header = TRUE;
$sql = query_result('SELECT `id`, `name` FROM `tree` WHERE `class` = '.OBJECT_TYPE_GAME);
while ($row = mysql_fetch_array($sql))
{
    $finished = query_val_by_key('finished', 'object_data_'.OBJECT_TYPE_GAME, 'id', $row['id']);

    if (($finished == 1) && is_game_finished_by_all($row['id']))
    {
        if ($header) { echo "<b>Статистика по прошедшим играм</b><br>"; $header = FALSE; }

        echo "<a href=gameflow.php?game={$row['id']}>{$row['name']}</a><br>";
    }
}

$ver = get_version();
echo "<br><br><span style=\"color:#333\">Игровой движок RECO, версия $ver.</span><br>";

echo "<iframe width=1 height=1 src=online.php style=\"border:0;padding:0;margin:0;position:relative;left:-20px;\"></iframe>";
