<?php

header("Content-Type: text/html; charset=utf-8");

set_include_path(get_include_path() . PATH_SEPARATOR . '../../libs/globaltree/');
include('helpers.php');
initAuth();
checkAuthPassive();

query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

admin_table_style();
secureGetRequestData('object', 'action', 'id', 'type');

if (!check_object_right($object, ACCESS_CHANGE_RIGHTS))
{
    die('У вас недостаточно прав для изменение прав доступа к этому объекту');
}

// NEW

if ($action == 'newu')
{
    $query = "SELECT `id`, `login` FROM `user`;";
    $sql = mysql_query($query) or die(mysql_error());
    while ($row = mysql_fetch_array($sql))
    {
        print "{$row['login']} (<a href=?action=newu2&object=$object&id={$row['id']}>выбрать)</a><br>";
    }
    print "Владелец объекта (<a href=?action=newu2&object=$object&id=".USER_TYPE_OWNER.">выбрать)</a><br>";
    print "Все пользователи (<a href=?action=newu2&object=$object&id=".USER_TYPE_ALL.">выбрать)</a><br>";
    exit;
}

if ($action == 'newu2')
{
    if (get_user_prio($id, $object) < get_current_prio()) { die('У вас недостаточно прав доступа для совершения данной операции.'); }
    if (get_user_group($id) == 1) { die('Нельзя вносить изменения прав доступа для пользователей из группы администраторы'); }

    $query = "INSERT INTO `access` SET `object` = '$object', `user` = '$id', `right` = '0', `defined` = '0';";
    $sql = mysql_query($query) or die(mysql_error());

    echo "<script>alert('ok');document.write('<meta http-equiv=refresh content=0;url=?object=$object>');</script>";
    exit;
}

if ($action == 'newg')
{
    $query = "SELECT `id`, `name` FROM `group`;";
    $sql = mysql_query($query) or die(mysql_error());
    while ($row = mysql_fetch_array($sql))
    {
        print "{$row['name']} (<a href=?action=newg2&object=$object&id={$row['id']}>выбрать)</a><br>";
        print "Группа владельца объекта (<a href=?action=newg2&object=$object&id=".USER_TYPE_OWNER.">выбрать)</a><br>";
    }
    exit;
}

if ($action == 'newg2')
{
    if (get_group_prio($id, $object) < get_current_prio()) { die('У вас недостаточно прав доступа для совершения данной операции.'); }
    if ($id == 1) { die('Нельзя вносить изменения прав доступа для группы администраторы'); }

    $query = "INSERT INTO `access` SET `object` = '$object', `group` = '$id', `right` = '0', `defined` = '0';";
    $sql = mysql_query($query) or die(mysql_error());

    echo "<script>alert('ok');document.write('<meta http-equiv=refresh content=0;url=?object=$object>');</script><br>"; 
    exit;
}

// DEL
if ($action == 'del')
{
    if ($type == 'u')
    {
        if (get_user_prio($id, $object) < get_current_prio()) { die('У вас недостаточно прав доступа для совершения данной операции.'); }
        if (get_user_group($id) == 1) { die('Нельзя вносить изменения прав доступа для пользователей из группы администраторы'); }

        $cond = " AND `user` = '$id'; ";
    }
    else if ($type == 'g')
    {
        if (get_group_prio($id, $object) < get_current_prio()) { die('У вас недостаточно прав доступа для совершения данной операции.'); }
        if ($id == 1) { die('Нельзя вносить изменения прав доступа для группы администраторы'); }

        $cond = " AND `group` = '$id'; ";
    }

    $query = "DELETE FROM `access` WHERE `object` = '$object' $cond";
    $sql = mysql_query($query) or die(mysql_error());

    echo "<script>alert('ok');document.write('<meta http-equiv=refresh content=0;url=?object=$object>');</script><br>"; 
    exit;
}

// EDIT

if ($action == 'edit')
{
    $rights = list_available_rights();

    if ($type == 'u')
    {
        if (get_user_prio($id, $object) < get_current_prio()) { die('У вас недостаточно прав доступа для совершения данной операции.'); }
        if (get_user_group($id) == 1) { die('Нельзя вносить изменения прав доступа для пользователей из группы администраторы'); }

        $name = 'пользователя '.get_user_name($id);
    }
    else if ($type == 'g')
    {
        if (get_group_prio($id, $object) < get_current_prio()) { die('У вас недостаточно прав доступа для совершения данной операции.'); }
        if ($id == 1) { die('Нельзя вносить изменения прав доступа для группы администраторы'); }

        $name = 'группы '.get_group_name($id);
    }

    $o_name = getName($object);

    echo "<h3>Права доступа $name к объекту \"$o_name\"</h3>";

    echo "<form method=post>";
    echo "<input type=hidden name=action value=edit2>";
    echo "<input type=hidden name=object value=$object>";
    echo "<input type=hidden name=type value=$type>";
    echo "<input type=hidden name=id value=$id>";

    echo "<table>";

    echo "<tr><td></td><td>Чтение</td><td>Запись</td><td>Создание подразделов</td><td>Удаление подразделов</td><td>Изменение прав</td></tr>";

    echo "<tr><td>Правило действительно?</td>";
    foreach ($rights as $right)
    {
        echo "<td>";
        $c = '';
    
        if ($type == 'u')
        {
            $a = check_local_object_right_for_user($object, $right, $id);
        } else if ($type == 'g')
        {
            $a = check_local_object_right_for_group($object, $right, $id);
        }

        if ($a != '')
        {
            $c = ' checked ';
        }
        echo "<input type=checkbox value=1 name=a_$right id=a_$right $c onchange=\"document.getElementById('r_$right').disabled=!this.checked;\">";
        echo "</td>";
    }
    echo "</tr>";

    echo "<tr><td>Действие разрешено?</td>";
    foreach ($rights as $right)
    {
        echo "<td>";
        $c = '';

        if ($type == 'u')
        {
            $a = check_local_object_right_for_user($object, $right, $id);
        } else if ($type == 'g')
        {
            $a = check_local_object_right_for_group($object, $right, $id);
        }

        if ($a)
        {
            $c = ' checked ';
        }

        echo "<input type=checkbox value=1 name=r_$right id=r_$right $c>";
        echo "</td>";
    }
    echo "</tr>";

    echo "</table>";

    echo "<input type=submit value=Сохранить></form>";

    echo "<script>";
    foreach ($rights as $right)
    {
        echo "document.getElementById('r_$right').disabled=!document.getElementById('a_$right').checked;";
    }
    echo "</script>";

    echo '<br>Если галочка "Правило действительно?" не установлена, то правило будет наследоваться от родительского объекта.';

    // fixme: при установке прав в disabled фиксировать их не в последнем значении, а в значении, загруженном с сервера. так нагляднее будет.

    exit;
}

if ($action == 'edit2')
{
    $rights = list_available_rights();

    $a = 0;
    $r = 0;
    foreach ($rights as $right)
    {
        if ((secRD('a_'.$right) == '1') && (secRD('r_'.$right) == '1')) { $r = $r | $right; }
        if (secRD('a_'.$right) == '1') { $a = $a | $right; }
    }

    if ($type == 'u')
    {
        if (get_user_prio($id, $object) < get_current_prio()) { die('У вас недостаточно прав доступа для совершения данной операции.'); }

        $subq = " `user` = '$id' ";
    }
    if ($type == 'g')
    {
        if (get_group_prio($id, $object) < get_current_prio()) { die('У вас недостаточно прав доступа для совершения данной операции.'); }

        $subq = " `group` = '$id' ";
    }

    $query = "UPDATE `access` SET `right` = '$r', `defined` = '$a' WHERE $subq AND `object` = '$object';";
    $sql = mysql_query($query) or die(mysql_error());

    echo "<script>alert('ok');document.write('<meta http-equiv=refresh content=0;url=?object=$object>');</script><br>"; 
    exit;

    exit;
}

// LIST

if (empty($action))
{
    print "<h3>Список пользователей и групп, для которых заданы права доступа</h3>";

    $query = "SELECT `user`, `group` FROM `access` WHERE `object` = '$object'";
    $sql = mysql_query($query) or die(mysql_error());

    print "<table border=1 cellpadding=4 width=550>";
    while ($row = mysql_fetch_array($sql))
    {
        if ($row['user'] != '') { $name = 'Пользователь "'.get_user_name($row['user']).'"'; $type='u'; $id = $row['user']; }
        else { $name = 'Группа "'.get_group_name($row['group']).'"'; $type='g'; $id = $row['group']; }

        print "<tr><td width=55%>$name</td>";
        print "<td width=10%><a href=?action=del&object=$object&id=$id&type=$type>Удалить</a></td>";
        print "<td width=35%><a href=?action=edit&object=$object&id=$id&type=$type>Изменить права доступа</a></td>";
    }
    print "</table>";

    print "<br><a href=?action=newu&object=$object>Задать права для другого пользователя</a><br>";
    print "<br><a href=?action=newg&object=$object>Задать права для другой группы</a><br>";

}


