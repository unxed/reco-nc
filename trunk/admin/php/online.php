<?php

// Check user session and send 'ok' if it is ok

session_start();
$sid_old = session_id();

set_include_path(get_include_path() . PATH_SEPARATOR . '../../libs/globaltree/');
include('helpers.php');
initAuth();
checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

$sid = session_id();

if ($sid != $sid_old) { exit; } // id сессии изменился. нужно перерисовать админку.

$query = "SELECT count(id) FROM `tree` WHERE (`last_modified` > (SELECT `tree_updated` FROM `session` WHERE `session_id` = '$sid')) AND (`modified_by` != '$sid');";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);

$cnt = $row[0];

$query = "SELECT count(id) FROM `log` WHERE (`ts` > (SELECT `tree_updated` FROM `session` WHERE `session_id` = '$sid')) AND (`modified_by` != '$sid');";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);

$cnt = $cnt + $row[0];

if ($cnt > 0)
{
    print "redraw";
} else
{
    print "ok";
}
