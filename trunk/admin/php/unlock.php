<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../../libs/globaltree/');
include('helpers.php');
initAuth();
checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('id');

// we are able only to unlock the current session's lock's

$sid = session_id();

$query = "UPDATE `tree` SET `lock` = NULL WHERE (`id` = '$id') AND (`lock` = '$sid');";
$sql = mysql_query($query) or die(mysql_error());
