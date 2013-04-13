<?php

header("Content-Type: text/html; charset=utf-8");

set_include_path(get_include_path() . PATH_SEPARATOR . '../../libs/globaltree/');
include('helpers.php');
initAuth();
checkAuthPassive();

query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

// for administrators only
if (get_current_users_group() != 1) { die('access denied'); }

admin_table_style();
secureGetRequestData('action', 'id', 'group', 'name');

if ($action == 'del')
{
    if (isAdmin())
    {
        $query = "SELECT `login` FROM `user` WHERE `id` = '$id'";
        $sql = mysql_query($query) or die(mysql_error());
        $row = mysql_fetch_array($sql);
        $name = $row['login'];

        if ($id == $_SESSION['user_id'])
        {
            $msg = "Невозможно удалить учетную запись, под которой Вы вошли в систему ('{$name}').<br>";
        }
        else
        {
            $msg = "Удаляем пользователя \"{$name}\"...";
            $query = "DELETE FROM `user` WHERE `id` = '$id'";
            $sql = mysql_query($query) or die(mysql_error());
        }
    } else {

        $msg = "Удаление пользователя возможно только при работе с привилегиями администратора.<br>";

    }

    echo "<script>alert('$msg');document.write('<meta http-equiv=refresh content=0;url=?>');</script><br>"; 

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

    exit;
}

if ($action == 'name')
{
    $old_name = query_val_by_key('name', 'user', 'id', $id);

    echo "Новое имя: <form method=post>";
    echo "<input type=hidden name=action value=name2>";
    echo "<input type=hidden name=id value=$id>";
    echo "<input name=name size=40 value=\"$old_name\"><br>";
    echo "<input type=submit value=Сохранить>";

    exit;
}

if ($action == 'name2')
{
    $query = "UPDATE `user` SET `name` = '$name' WHERE `id` = '$id';";
    $sql = mysql_query($query) or die(mysql_error());

    echo "<script>alert('ok');document.write('<meta http-equiv=refresh content=0;url=?>');</script><br>"; 

    exit;
}

if ($action == 'logout_user')
{
    $sql = query_result("SELECT `session_id` FROM `session` WHERE `user_id` = '$id'");
    while ($row = mysql_fetch_array($sql))
    {
        if ($row['session_id'] != session_id()) // себя разлогинивать не будем
        {
            dropSessionWithLocks($row['session_id']);
        }
    }

    echo "<script>alert('ok');document.write('<meta http-equiv=refresh content=0;url=?>');</script><br>"; 

    exit;
}

if (empty($action))
{
    print "<h3>Список пользователей</h3>";

    $query = "SELECT `id`, `login` FROM `user`";
    $sql = mysql_query($query) or die(mysql_error());

    print "<table border=1 cellpadding=4 width=400>";
    while ($row = mysql_fetch_array($sql))
    {
        $name = query_val_by_key('name', 'user', 'id', $row['id']);
        $group_name = get_user_group_name($row['id']);
        if ($group_name == '') { $group_name = 'не&nbsp;определена'; }

        print "<tr><td>$name (логин {$row['login']}, группа&nbsp;$group_name)</td>";
        print "<td><a href=?action=del&id={$row['id']}>Удалить</a></td>";
        print "<td><a href=newpass.php?id={$row['id']}>Изменить&nbsp;пароль</a></td>";
        print "<td><a href=?action=group&id={$row['id']}>Изменить&nbsp;группу</a></td>";
        print "<td><a href=?action=name&id={$row['id']}>Изменить&nbsp;имя</a></td>";
        print "<td><a href=?action=logout_user&id={$row['id']}>Разлогинить</a></td></tr>";
    }
    print "</table>";

    print "<br><a href=register.php>Новый пользователь</a><br>";
}
