<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../libs/globaltree/');
include('helpers.php');
initAuth();
checkAuth();

query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

secureGetRequestData('name', 'template', 'data_source', 'visible');

if ($name == '')
{

print "
<form method=post>
Название нового класса объектов:<br>
<input name=name><br>
Внутреннее имя (для названия плагина обработки данных и имени файла шаблона; английские буквы и цифры, не больше 8):<br>
<input name=data_source><br>
<!--
Имя шаблона, используемого для отображения объектов этого класса:<br>
<input name=template><br>
-->
Видимость (отображать в меню сайта и т.д. - 1, служебный тип данных, не предназначенный для прямого вывода пользователю - 0):<br>
<input name=visible><br>
<input type=submit>
</form>
";

} else {

$query = "SELECT id FROM object_class WHERE (name = '$name');";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
$row = mysql_fetch_array($sql);
if ($row[0] != '')
{
    print "Повторное добавление невозможно.";
    die;
}

if ($template == '') { $template = $data_source . '.tpl'; }

// создадим новую запись в object_class, и заполним ее

$query = "INSERT INTO object_class (id) VALUES (0);";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);

$query = "SELECT id FROM object_class WHERE id = LAST_INSERT_ID();";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
$row = mysql_fetch_array($sql);
$newId = $row['id'];

$tblName = "object_data_$newId";

$query = "UPDATE object_class SET name = '$name', data_source = '$data_source', template = '$template', table_name = '$tblName', add_allowed = '1' WHERE id = '$newId';";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);

if (($visible != '') && ($visible != '0'))
{
    $query = "UPDATE `object_class` SET `visible` = '1' WHERE id = '$newId';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
}

// создадим таблицу данных

$query = "CREATE TABLE `$tblName` (id INT PRIMARY KEY);";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);

print "ok";

}
