<?php

// Save data

set_include_path(get_include_path() . PATH_SEPARATOR . '../../libs/globaltree/');
include('helpers.php');
initAuth();
checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('id');

if (!check_object_right($id, ACCESS_WRITE)) { die('access denied'); }

$query = "SELECT `class` FROM `tree` WHERE `id` = '$id'";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);
$class = $row['class'];

$query = "SELECT `table_name` FROM `object_class` WHERE `id` = '$class'";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);
$tableName = $row['table_name'];

$query = "SELECT `id`, `type`, `table_field`, `is_name` FROM `object_property` WHERE `object_class_id` = '$class'";
$sql2 = mysql_query($query) or die(mysql_error());
while ($row2 = mysql_fetch_array($sql2))
{
    $elementId = $row2['id'];
    $tableField = $row2['table_field'];
    // этот вариант ломает " в текстах, сохраняемых в базу
    // но вообще, это надо проверить на свежую голову
    // $c = mysql_real_escape_string(secRD('el'.$elementId));
    if (isset($_REQUEST['el'.$elementId])) {    
        $c = mysql_real_escape_string($_REQUEST['el'.$elementId]);
    } else {
        $c = '';
    }

    // datatype
    switch ($row2['type'])
    {
        case 1:
            $c = substr($c, 2, strlen($c) - 2); // remove 'id' prefixes

        case 13:
            $c = str_replace('id', '', $c); // remove 'id' prefixes

        case 2:
        case 3:
        case 10:
        case 11:
        case 12:
        case 13:
        case 14:
        case 15:

            // checkbox case
            if ($row2['type'] == 15) {
                if ($c == 'on') { $c = 1; } else { $c = 0; }
            }

            // использование поля name в tree вместо отдельного поля - только для типа 3 ("строка")
            if (($row2['is_name'] == 1) && ($row2['type'] == 3))
            {
                $query = "UPDATE `tree` SET `name` = '$c' WHERE `id` = '$id'";
                if (!($sql = mysql_query($query))) die(mysql_error());

                treeUpdate($id);
            } else
            {
                $query = "SELECT `id` FROM `$tableName` WHERE `id` = '$id'";
                $sql = mysql_query($query) or die(mysql_error());
                $row = mysql_fetch_array($sql);
                if ($row['id'] == '')
                {
                    $query = "INSERT INTO `$tableName` (`id`) VALUES ('$id')";
                    $sql = mysql_query($query) or die(mysql_error());
                }
                
                $query = "UPDATE `$tableName` SET `$tableField` = '$c' WHERE `id` = '$id'";
                $sql = mysql_query($query) or die(mysql_error());
            }

            if ( (($row2['type'] == 11) || ($row2['type'] == 12)) && ($c == '0x0') )
            {
                $dest = '../storage/'.$id.'_'.$row2['id'].'.swf';
                if (file_exists($dest))
                {
                    unlink($dest);
                }

                $dest = '../storage/'.$id.'_'.$row2['id'].'.flv';
                if (file_exists($dest))
                {
                    unlink($dest);
                }
            }

            break;
        case 4:
            $query = "SELECT `id` FROM `photo` WHERE `reference` = '$id' AND `element_id` = '$elementId'";
            $sql = mysql_query($query) or die(mysql_error());
            while ($row = mysql_fetch_array($sql))
            {
                $imgId = $row['id'];
                $name = secRD('el'.$elementId.'img'.$imgId.'name');
                $href = secRD('el'.$elementId.'img'.$imgId.'href');
                //if ($name != '')
                {
                    $query = "UPDATE `photo` SET `name` = '$name' WHERE `id` = '$imgId'";
                    $sql3 = mysql_query($query) or die(mysql_error());
                }
                //if ($href != '')
                {
                    $query = "UPDATE `photo` SET `href` = '$href' WHERE `id` = '$imgId'";
                    $sql3 = mysql_query($query) or die(mysql_error());
                }
            }

            break;
    }
}

echo 'ok::' . getName($id);
