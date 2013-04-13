<?php

// List menu items

set_include_path(get_include_path() . PATH_SEPARATOR . '../../libs/globaltree/');
include('helpers.php');
initAuth();
checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('id');

if ($id == '') { $id = 1; }

if (!check_object_right($id, ACCESS_READ)) { die('access denied'); }

if ($id == 1)
{
    // если запрошено обновление всего дерева
    // обновим данные о последнем обновлении дерева
    // в теории, между этим действием и сканированием дерева (makelist)
    // нужно блокировать возможность изменения дерева.
    // на практике это чревато только тем, что есть между фиксацией момента обновления
    // и сканированием произойдут изменения,
    // эти изменения будут перезапрошены с сервера при следующем обновлении.
    // т.е. это вредно только при очень большом количестве обновлений.

    $sid = session_id();
    $query = "UPDATE session SET tree_updated = NOW() WHERE session_id = '$sid';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
}

makelist($id, getLevel($id));

function makelist($id, $level)
{
    $level++;

    $query = "SELECT `id`, `parent`, `class` FROM `tree` WHERE `parent` = '$id' ORDER BY `order_token`";
    $sql = mysql_query($query) or die(mysql_error());

    while ($row = mysql_fetch_array($sql))
    {
        if (check_object_right($row['id'], ACCESS_READ))
        {
            $name = getName($row[id]);

            $add_allowed = query_val_by_key('add_allowed', 'object_class', 'id', $row['class']);
            if ($add_allowed != '1') { $add_allowed = '0'; }

            $visible = query_val_by_key('visible', 'object_class', 'id', $row['class']);
            if ($visible != '1') { $visible = '0'; }

          echo "$row[parent]::$row[id]::$name::$level::$add_allowed::$visible\n";
            makelist($row[id], $level);
        }
    }
}

