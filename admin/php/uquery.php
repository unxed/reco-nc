<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../../libs/globaltree/');
include('helpers.php');
initAuth();
checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('id', 'token');
$objClass = secRD('class');

if (!check_object_right($id, ACCESS_READ)) { die('access denied'); }

$var = array();

$query = "SELECT `name`, `table_name` FROM `object_class` WHERE `id` = '$objClass'";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
$row = mysql_fetch_array($sql);
$objClassName = $row['name'];
$tableName = $row['table_name'];

$query = "SELECT `id`, `name`, `type`, `table_field`, `list_children`, `is_name`, `maxcnt`, `img_desc` FROM `object_property` WHERE `object_class_id` = '$objClass' ORDER BY order_token";
$sql2 = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
while ($row2 = mysql_fetch_array($sql2))
{
    $tableField = $row2['table_field'];
    $sub = $row2['list_children'];
    $elementId = $row2['id'];
    $type = $row2['type'];
    $name = $row2['name'];
    $maxCnt = $row2['maxcnt'];
    $imgDesc = $row2['img_desc'];
    $isName = $row2['is_name'];

    $list = '';

    // datatype
    switch ($type)
    {
        // выпадающий список. отрицательный sub означает выбор из объектов родительского уровня,
        // причем -1 - внутри нашего уровеня, -2 - внутри родительского (т.е. наш) и т.д.
        // положительный - четкий id объекта, среди потомков которой нужно выбирать
        // в $list помещается хэш-массив возможных значений, из которых мы выбираем
        // fixme: вариант при sub = 0 пока не рассматривается
        // флаг subFlag указывает на то, что значение sub так или иначе соответствует режиму "<select> в режиме выбора из списка объектов"
        case 1:
            $subFlag = false;

            if ($sub < 0)
            {
                $tmpId = $id;

                $sub++;
                while ($sub < 0)
                {
                    $query = "SELECT `parent` FROM `tree` WHERE `id` = '$tmpId'";
                    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
                    $row = mysql_fetch_array($sql);
                    $tmpId = $row['parent'];
                    $sub++;
                }

                $subFlag = true;
            }
            else if ($sub > 0)
            {
                $tmpId = $sub;
                $subFlag = true;
            }

            if ($subFlag)
            {
                $list = array();

                $query = "SELECT `id`, `name` FROM `tree` WHERE `parent` = '$tmpId'";
                $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
                while ($row = mysql_fetch_array($sql))
                {
                    // fixme: исключать себя из списка вариантов для выбора
                    $list['id'.$row['id']] = $row['name'];

                    //debug print "** $row[id] ** $row[name] **\n";
                }

            } else if ($sub == 0) // вывести все возможные объекты
            {
                $list = listAllObjs();
            }


            if ($tableField != '')
            {
                $query = "SELECT `$tableField` FROM `$tableName` WHERE `id` = '$id'";
                $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__.", $query");
                $row = mysql_fetch_array($sql);

                $tmpVal = $row[$tableField];
                if ($tmpVal == '') { $tmpVal = 0; } // for undefined select options

                $value = 'id' . $tmpVal;
            }

            $undef = array('id0' => '----------'); // for undefined select options
            $list = array_merge($undef, $list);

            $var[] = array('list' => $list, 'value' => $value, 'type' => $type, 'name' => $name, 'id' => $elementId, 'isName' => $isName);

            break;
        case 2:
            if ($tableField != '')
            {
                $query = "SELECT `$tableField` FROM `$tableName` WHERE `id` = '$id'";
                $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__.", $query");
                $row = mysql_fetch_array($sql);

                $value = $row[$tableField];
            }

            $value = htmlspecialchars($value);

            $var[] = array('value' => $value, 'type' => $type, 'name' => $name, 'id' => $elementId);
            break;
        case 13:
            $list = array();

            $query = "SELECT `id`, `name` FROM `tree` WHERE `parent` = '$sub'";
            $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
            while ($row = mysql_fetch_array($sql))
            {
                // fixme: исключать себя из списка вариантов для выбора
                $list['id'.$row['id']] = $row['name'];
            }

        case 3:
        case 10:
        case 14:
        case 15:
            if ($tableField != '')
            {
                $query = "SELECT `$tableField` FROM `$tableName` WHERE `id` = '$id'";
                $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
                $row = mysql_fetch_array($sql);

                $value = $row[$tableField];
            } else if ($isName)
            {
                $query = "SELECT `name` FROM `tree` WHERE `id` = '$id'";
                $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
                $row = mysql_fetch_array($sql);

                $value = $row['name'];
            }

            $value = htmlspecialchars($value);

            $var[] = array('value' => $value, 'type' => $type, 'name' => $name, 'id' => $elementId, 'isName' => $isName, 'list' => $list);

            break;
        case 4:
            $images = array();

            $count = 0;
            $query = "SELECT `id`, `name`, `href` FROM `photo` WHERE `reference` = '$id' AND `element_id` = '$elementId' ORDER BY order_token";
            $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
            while ($row = mysql_fetch_array($sql))
            {
                $count++;
                $images['id'.$count] = array('id' => $row['id'], 'name' => $row['name'], 'href' => $row['href']);
            }

            if (($maxCnt == '') || ($maxCnt == 0)) { $maxCnt = 500; }
            if ($imgDesc == '') { $imgDesc = 0; }
            
            $var[] = array('images' => $images, 'type' => $type, 'name' => $name, 'id' => $elementId, 'maxcnt' => $maxCnt, 'imgDesc' => $imgDesc, 'count' => count($images));

            break;
        case 8:
        case 9:
            $var[] = array('type' => $type, 'name' => $name, 'id' => $elementId);
            break;
        case 11:
        case 12:
            $query = "SELECT `$tableField` FROM `$tableName` WHERE `id` = '$id'";
            $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
            $row = mysql_fetch_array($sql);

            $value = $row[$tableField];

            list($w, $h) = explode('x', $value);

            if (intval($w) == '') { $w = 0; }
            if (intval($h) == '') { $h = 0; }

            $var[] = array('type' => $type, 'name' => $name, 'id' => $elementId, 'w' => $w, 'h' => $h);
            break;
    }
}

$out = array('name' => $objClassName, 'elements' => $var, 'token' => $token);

print json_encode($out);

function listAllObjs($parent = 0, $prefix = '')
{
    $list = array();
    // if ($parent == 0) { $list['unset'] = '-'; }

    $query = "SELECT `id`, `name` FROM `tree` WHERE `parent` = '$parent' ORDER BY order_token";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    while ($row = mysql_fetch_array($sql))
    {
        // fixme: исключать себя из списка вариантов для выбора
        $list['id'.$row['id']] = $prefix . $row['name'];

        $list = $list + listAllObjs($row['id'], '&nbsp;&nbsp;&nbsp;&nbsp;'.$prefix);
    }

    return $list;
}
