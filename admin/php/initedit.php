<?php

include ('../../libs/globaltree/auth.php');
checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('id');
$sid = session_id();

if (!check_object_right($id, ACCESS_READ)) { die('access denied'); }

$query = "SELECT `lock` FROM `tree` WHERE id = '$id';";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);
if (($row['lock'] != '') && ($row['lock'] != $sid))
{
    // this id is lock by another user
    echo '-1';
    exit();
}

$query = "UPDATE tree SET `lock` = '$sid' WHERE id = '$id';";
$sql = mysql_query($query) or die(mysql_error());

$query = "SELECT class FROM tree WHERE id = '$id'";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);

echo "$row[0]";

