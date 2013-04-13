<?php

// Create new tree leaf and return it's id

set_include_path(get_include_path() . PATH_SEPARATOR . '../../libs/globaltree/');
include('helpers.php');
initAuth();
checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('parent', 'class');

$t = win2utf($langdata['default_title']);
if ($parent == '') { $parent = 1; }

if (!check_object_right($parent, ACCESS_CREATE)) { die('access denied'); }

// проверим, можно ли добавлять в выбранный объект вложенный объект выбранного типа
$allowed_str = query_val_by_key('allowed_parents', 'object_class', 'id', $class);
$allowed = explode(',', $allowed_str);
if (!(in_array(query_val_by_key('class', 'tree', 'id', $parent), $allowed)) && ($allowed_str != ''))
{
        die('invalid_parent_fail');
}

$query = "SELECT MAX(id) + 1 FROM `tree`";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);
$id = $row[0];

if (($id == '') || ($id == 0)) { $id = 1; }

$query = "SELECT MAX(order_token) FROM tree WHERE parent = '$parent'";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);
$max = $row[0];

if ($max == '') { $max = 0; }
$max++;

$sid = session_id();

$query = "INSERT INTO `tree` (id, parent, name, class, order_token, last_modified, modified_by, owner)
        VALUES ('$id', '$parent', '$t', '$class', '$max', NOW(), '$sid', '{$_SESSION['user_id']}')";
if (!($sql = mysql_query($query))) die(mysql_error());

// Проставим значения по умолчанию
$name = 'No name';
$qr = query_result("SELECT `id`, `table_field`, `is_name` FROM `object_property` WHERE `object_class_id` = '$class' AND `default_value` <> '';");
while ($row = mysql_fetch_array($qr))
{
    $content_id = query_val_by_key('id', 'object_data_' . $class, 'id', $id);
    if ($content_id == '')
    {
        query_result("INSERT INTO `object_data_" . $class."` SET `id` = '$id';");
    }

    $value = get_default_value($id, $row['id']);

    if ($row['is_name'] == 1)
    {
        query_result("UPDATE `tree` SET `name` = '$value' WHERE `id` = '$id';");
        $name = $value;
    } else {
        query_result("UPDATE `object_data_" . $class."` SET `{$row['table_field']}` = '$value' WHERE `id` = '$id';");    
    }
}

apply_default_rights($id);

// определим уровень вложенности нового элемента
$level = getLevel($parent) + 1;

echo "$id:$level:$name";
