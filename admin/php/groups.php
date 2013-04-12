<?php

header("Content-Type: text/html; charset=utf-8");

include ('../../libs/globaltree/auth.php');
checkAuthPassive();

query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

// for administrators only
if (get_current_users_group() != 1) { die('access denied'); }

admin_table_style();
secureGetRequestData('action', 'id', 'name', 'prio');

if ($action == 'del')
{
    $query = "DELETE FROM `group` WHERE `id` = '$id';";
    $sql = mysql_query($query) or die(mysql_error());

    echo "<script>alert('ok');document.write('<meta http-equiv=refresh content=0;url=?object=$object>');</script><br>"; 

    exit;
}

if ($action == 'group')
{
    $query = "SELECT `id`, `name` FROM `group`;";
    $sql = mysql_query($query) or die(mysql_error());
    while ($row = mysql_fetch_array($sql))
    {
        print "{$row['name']} (<a href=?action=group2&id=$id&group={$row['id']}>выбрать)</a><br>";
    }

    exit;
}

if ($action == 'group2')
{
    $query = "UPDATE `user` SET `group` = '$group' WHERE `id` = '$id';";
    $sql = mysql_query($query) or die(mysql_error());

    echo "<script>alert('ok');document.write('<meta http-equiv=refresh content=0;url=?>');</script><br>"; 
}

if ($action == 'new')
{
    echo "<form method=post>";
    echo "<input type=hidden name=action value=new2>";
    echo "Имя группы:<br>";
    echo "<input name=name size=40><br>";
    echo "Приоритет:<br>";
    echo "<input name=prio size=4 value=255><br>";
    echo "<input type=submit value=\"Добавить группу\">";
    echo "</form>";

    exit;
}

if ($action == 'new2')
{
    // Нельзя задавать приоритет больше администраторского (1) (меньшее значение - больший приоритет)
    if ($prio < 1) { $prio = 1; }

    $query = "INSERT INTO `group` SET `name` = '$name', `prio` = '$prio';";
    $sql = mysql_query($query) or die(mysql_error());

    echo "<script>alert('ok');document.write('<meta http-equiv=refresh content=0;url=?>');</script><br>"; 
}

if ($action == 'prio')
{
    $group_name = get_group_name($id);
    $prio = get_group_prio($id);

    echo "<form method=post>";
    echo "<input type=hidden name=action value=prio2>";
    echo "Имя группы:<br>";
    echo "<input name=name size=40 value=\"$group_name\"><br>";
    echo "Приоритет:<br>";
    echo "<input name=prio size=4 value=\"$prio\"><br>";
    echo "<input type=submit value=\"Сохранить изменения\">";
    echo "</form>";

    exit;
}

if ($action == 'prio2')
{
    $query = "UPDATE `group` SET `name` = '$name', `prio` = '$prio' WHERE `id` = '$id';";
    $sql = mysql_query($query) or die(mysql_error());

    echo "<script>alert('ok');document.write('<meta http-equiv=refresh content=0;url=?>');</script><br>"; 
}

if (empty($action))
{
    print "<h3>Список групп</h3>";

    $query = "SELECT `id`, `name`, `prio` FROM `group`";
    $sql = mysql_query($query) or die(mysql_error());

    print "<table border=1 cellpadding=4 width=400>";
    print "<tr><td>Группа</td><td>Приоритет</td><td></td><td></td></tr>";
    while ($row = mysql_fetch_array($sql))
    {
        $group_name = get_group_name($row['id']);
        if ($group_name == '') { $group_name = 'Без&nbsp;названия'; }

        print "<tr><td>{$row['name']}</td>";
        print "<td>{$row['prio']}</td>";
        print "<td><a href=?action=del&id={$row['id']}>Удалить</a></td>";
        print "<td><a href=?action=prio&id={$row['id']}>Изменить</a></td></tr>";
    }
    print "</table>";

    print "<br><a href=?action=new>Новая группа</a><br>";

    print "<br>Приоритет считается по принципу \"чем меньше число - тем выше приоритет\".";
}
