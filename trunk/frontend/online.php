<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../libs/globaltree/');
include('helpers.php');
include('playhelp.php');

initAuth();

if (!checkAuthQuiet())
{
    echo '<script>top.location.reload(true);</script>';
    exit;
}

$user = $_SESSION['user_id'];

$row = query_1st_row("SELECT COUNT(`id`) FROM `game_request` WHERE `user` = '$user' AND `force_refresh` = '1'");
if ($row[0] > 0)
{
    query_result("UPDATE `game_request` SET `force_refresh` = '0' WHERE `user` = '$user'");

    setcookie('r', 'true');
    setcookie('rt', 10);
    echo '<script>top.location.reload(true);</script>';
    exit;
}

echo '<meta http-equiv=refresh content='.CFG_CHECK_ONLINE_PERIOD.'>';

echo 'ok';


function checkAuthQuiet()
{
    $result = TRUE;

    if (!isset($_SESSION['user_id']))
    {
        $result = FALSE;
    }

    // Если в базе нет такой сессии, удалим её из cookies
    $sid = session_id();
    $row = query_1st_row("SELECT COUNT(`session_id`) FROM `session` WHERE `session_id` = '$sid'");
    if ($row[0] == 0)
    {
        destroySession();
        $result = FALSE;
    }

    if ($result)
    {
        updTs();
    }

    return $result;
}
