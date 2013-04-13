<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../libs/globaltree/');
include('helpers.php');
initAuth();
checkAuth();

query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

secureGetRequestData('type', 'name', 'object_class_id', 'is_name', 'img_desc', 'table_field', 'list_children', 'maxcnt', 'img_prop');

if ($type == '')
{

print "
<form method=post>
Класс объекта, в который мы добавляем поле:<br>
<select name=object_class_id>
";

$query = "SELECT `id`, `name` FROM `object_class`";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
while ($row = mysql_fetch_array($sql))
{
    print "<option value=$row[0]>$row[1]";
}

print "
</select><br>
Имя поля:<br>
<input name=name><br>
Тип поля<br>
<select name=type>
";

$typeList = listAvaliableTypes();

foreach ($typeList as $key => $value)
{
    $typeName = getTypeName($value);
    print "<option value=$value>$value/$typeName\n";
}

print "
</select><br>
Поле таблицы, используемое для хранения значений элемента:<br>
<input name=table_field><br>
Id раздела, на подразделы которого может ссылаться ссылка (для типа 1, -1 - этот, -2 - родительский и т.д.):<br>
<input name=list_children><br>
Это поле - имя объекта?<br>
<input name=is_name type=checkbox value=1><br>
Ограничение на количество изображений в галерее (для типа 4, число):<br>
<input name=maxcnt><br>
Использовать для описания изображений textarea? (для типа 4, в противном случае будет input):<br>
<input name=img_desc type=checkbox value=1><br>
Настройки превью (для типа 4, 'width, height, type, prefix[, crop, forceW, forceH, jpeg_quality];повтор', type: gif-1, jpeg-2, png-3):<br>
<input size=72 name=img_prop><br>
<input type=submit>
</form>
";

} else {

$query = "SELECT id FROM object_property WHERE (name = '$name') AND (object_class_id = '$object_class_id');";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
$row = mysql_fetch_array($sql);
if ($row[0] != '')
{
    print "Повторное добавление невозможно.";
    die;
}

// fix possibly wrong values of checkbox fields

if ($is_name != '1') { $is_name = '0'; }
if ($img_desc != '1') { $img_desc = '0'; }

// fix potentially wrong combinations

if ($is_name == '1') { $table_field = ''; }

// actually add data to DB

$query = "SELECT MAX(order_token) + 1 FROM object_property WHERE object_class_id = '$object_class_id';";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
$row = mysql_fetch_array($sql);
$max = $row[0]; if (($max == 0) || ($max == '')) { $max = 1; }

$query = "SELECT table_name FROM object_class WHERE id = '$object_class_id';";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
$row = mysql_fetch_array($sql);
$table = $row[0];

$query = "INSERT INTO object_property (name, object_class_id, type, order_token) VALUES " .
    "('$name', '$object_class_id', '$type', '$max');";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);

//
// Заполним стандартные поля.
//

if ($table_field != '')
{
    $query = "UPDATE object_property SET table_field = '$table_field' WHERE id = LAST_INSERT_ID();";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
}
if ($list_children != '')
{
    $query = "UPDATE object_property SET list_children = '$list_children' WHERE id = LAST_INSERT_ID();";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
}
if ($is_name != '')
{
    $query = "UPDATE object_property SET is_name = '$is_name' WHERE id = LAST_INSERT_ID();";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
}
if ($maxcnt != '')
{
    $query = "UPDATE object_property SET maxcnt = '$maxcnt' WHERE id = LAST_INSERT_ID();";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
}
if ($img_desc != '')
{
    $query = "UPDATE object_property SET img_desc = '$img_desc' WHERE id = LAST_INSERT_ID();";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
}

//
// Обработаем поле свойств изображения.
// Только для типа DATA_TYPE_IMG_LIST
//

$prop = $img_prop;
if ($prop != '')
{
    $query = "SELECT id FROM object_property WHERE id = LAST_INSERT_ID();";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);
    $newElId = $row['id'];

    $prop = preg_replace('/\s/', '', $prop);
    $items = explode(';', $prop);
    foreach ($items as $item)
    {
        // example: "180, 100, 2, pre2_" or "180, 100, 2, pre_2; 270, 160, 3, pre3_, 1, 0, 0, 90"

        list($iwidth, $iheight, $itype, $iprefix, $icrop, $iforceW, $iforceH, $ijpeg_quality) = explode(',', $item);

        $query = "INSERT INTO photo_gallery (object_property_id, width, height, type, prefix) VALUES ('$newElId', '$iwidth', '$iheight', '$itype', '$iprefix');";
        $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);

        if ($icrop != '')
        {
            $query = "UPDATE photo_gallery SET crop = '$icrop' WHERE id = LAST_INSERT_ID();";
            $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        }
        if ($iforceW != '')
        {
            $query = "UPDATE photo_gallery SET forceW = '$iforceW' WHERE id = LAST_INSERT_ID();";
            $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        }
        if ($iforceH != '')
        {
            $query = "UPDATE photo_gallery SET forceH = '$iforceH' WHERE id = LAST_INSERT_ID();";
            $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        }
        if ($ijpeg_quality != '')
        {
            $query = "UPDATE photo_gallery SET jpeg_quality = '$ijpeg_quality' WHERE id = LAST_INSERT_ID();";
            $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        }
    }
}

//
// Создадим поле базы данных для хранения значений элементов данного типа.
//
// Для img_list заполненное поле table_field реально не должно создавать поле в таблице, т.к.
// оно используется только для удобства именования объектов шаблонизатора.
//
if (($table_field != '') && ($type != DATA_TYPE_IMG_LIST))
{
    $sql_type = getTypeSQLType($type);

    if ($table == '')
    {
        $table = 'object_data_' . $object_class_id;

        $query = "UPDATE `object_class` SET `table_name` = '$table' WHERE `id` = '$object_class_id';";
        $sql = mysql_query($query);// or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__.", query '$query'");
    }

    if ($sql_type != '')
    {
        $query = "CREATE TABLE `$table` (`id` INT PRIMARY KEY);";
        $sql = mysql_query($query);// or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__.", query '$query'");

        $query = "ALTER TABLE `$table` ADD `$table_field` $sql_type";
        $sql = mysql_query($query);// or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    }
}

print "ok";

}
