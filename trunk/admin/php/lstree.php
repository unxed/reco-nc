<?php

include ('../../libs/globaltree/auth.php');
checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('ids');

$count = 0;
$locked = 0;

$idList = explode(',',$ids);
foreach ($idList as $id) { doList($id); }

echo ":$count:$locked";

function doList($id)
{
    global $count, $locked;

    $query = "SELECT `id` FROM `tree` WHERE parent = '$id'";
    $sql = mysql_query($query) or die(mysql_error());
    while ($row = mysql_fetch_array($sql))
    {
        doList($row['id']);
    }

    $query = "SELECT `lock` FROM `tree` WHERE id = '$id'";
    $sql = mysql_query($query) or die(mysql_error());
    $row = mysql_fetch_array($sql);
    if (($row['lock'] != '') && ($row['lock'] != session_id())) { $locked = 1; }

    $query = "SELECT count(id) FROM `photo` WHERE reference = '$id'";
    $sql = mysql_query($query) or die(mysql_error());
    $row = mysql_fetch_array($sql);
    $count += $row[0];

    echo $id . ',';
}
