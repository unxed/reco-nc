<?php

define("ACCESS_READ", 1);
define("ACCESS_WRITE", 2);
define("ACCESS_CREATE", 4);
define("ACCESS_DELETE", 8);
define("ACCESS_CHANGE_RIGHTS", 16);

define("USER_TYPE_OWNER", -1);
define("USER_TYPE_ALL", -2);

define("RIGHT_TYPE_USER", 1);
define("RIGHT_TYPE_GROUP", 2);
define("RIGHT_TYPE_ALL", 4);

function list_available_rights()
{
    return array(ACCESS_READ, ACCESS_WRITE, ACCESS_CREATE, ACCESS_DELETE, ACCESS_CHANGE_RIGHTS);
}

//
// Проверяет, имеет ли текущий или указанный пользователь или группа
// указанное право доступа к указанному объекту
// Если права не установлены, возвращает NULL
//
function check_object_right($object, $right, $user = NULL)
{
    $result = check_object_right_by_type($object, $right, RIGHT_TYPE_USER, get_owner($object), TRUE, $user, $group);
    if ($result != '')
    {
        return $result;
    }

    $result = check_object_right_by_type($object, $right, RIGHT_TYPE_GROUP, get_owner($object), TRUE, $user, $group);
    if ($result != '')
    {
        return $result;
    }

    $result = check_object_right_by_type($object, $right, RIGHT_TYPE_ALL, get_owner($object), TRUE, $user, $group);
    if ($result != '')
    {
        return $result;
    }
}

//
// Проверяет локальное право достута к объекту для пользователя (без учета наследования и приоритетов прав)
//
function check_local_object_right_for_user($object, $right, $user)
{
    if ($user == USER_TYPE_ALL)
    {
        return check_object_right_by_type($object, $right, RIGHT_TYPE_ALL, get_owner($object), FALSE, $user, NULL);
    } else {
        return check_object_right_by_type($object, $right, RIGHT_TYPE_USER, get_owner($object), FALSE, $user, NULL);
    }
}

//
// Проверяет локальное право достута к объекту для группы (без учета наследования и приоритетов прав)
//
function check_local_object_right_for_group($object, $right, $group)
{
    return check_object_right_by_type($object, $right, RIGHT_TYPE_GROUP, get_owner($object), FALSE, NULL, $group);
}

//
// Проверяет право доступа к объекту, локально или с учетом наследования, для пользователя или группы
// По умолчанию - текущий пользователь и его группа
//

function check_object_right_by_type($object, $right, $type, $owner, $use_parent_rights = true, $user = NULL, $group = NULL)
{
    // определим унаследованное право, если оно есть

    $parent = query_val_by_key('parent', 'tree', 'id', $object);
    if (($parent != 0) && ($use_parent_rights))
    {
        $parent_result = check_object_right_by_type($parent, $right, $type, $owner);
    }

    // определим локальное право

    // owner'а мы должны использовать от корневого объекта, который мы проверяем
    // $owner = get_owner($object);
    if ($user == '') { $user = $_SESSION['user_id']; }
    if ($group == '') { $group = get_user_group($user); }
    $owners_group = get_user_group($owner);

    if (($type == RIGHT_TYPE_USER) && ($user != USER_TYPE_ALL))
    {
        // для текущего пользователя

        $query = "SELECT `right` FROM `access` WHERE `object` = '$object' AND (`user` = '$user' OR (`user` = '".USER_TYPE_OWNER."' AND '$owner' = '$user')) AND (BIT_COUNT(`defined` & '$right') > 0);";
        $sql = mysql_query($query) or die(mysql_error());
        $row = mysql_fetch_array($sql);
        $result = check_right($row['right'], $right);

    } else if (($type == RIGHT_TYPE_GROUP) && ($user != USER_TYPE_ALL))
    {
        // для группы текущего пользователя

        $query = "SELECT `right` FROM `access` WHERE `object` = '$object' AND (`group` = '$group' OR (`group` = '".USER_TYPE_OWNER."' AND '$owners_group' = '$group')) AND (BIT_COUNT(`defined` & '$right') > 0);";
        $sql = mysql_query($query) or die(mysql_error());
        $row = mysql_fetch_array($sql);
        $result = check_right($row['right'], $right);

    } else if ($type == RIGHT_TYPE_ALL)
    {
        // для всех пользователей

        $query = "SELECT `right` FROM `access` WHERE `object` = '$object' AND (`user` = '".USER_TYPE_ALL."') AND (BIT_COUNT(`defined` & '$right') > 0);";
        $sql = mysql_query($query) or die(mysql_error());
        $row = mysql_fetch_array($sql);
        $result = check_right($row['right'], $right);

    }

    if (($result == '') && ($use_parent_rights))
    {
        return $parent_result;
    }

    return $result;
}

// Вспомогательные функции

function access_right_all()
{
    return ACCESS_READ | ACCESS_WRITE | ACCESS_CREATE | ACCESS_DELETE | ACCESS_CHANGE_RIGHTS;
}

function check_right($right, $mask)
{
    if ($right == '') { return; }
    if ($mask == '') { return; }

    if (($right & $mask) > 0)
    {
        return '1';
    } else {
        return '0'; // because 0 == NULL == ''
    }
}

function get_user_group($user)
{
    return query_val_by_key('group', 'user', 'id', $user);
}

function get_owner($object)
{
    return query_val_by_key('owner', 'tree', 'id', $object);
}

function get_user_name($id)
{
    if ($id == USER_TYPE_OWNER)
    {
        return 'Владелец объекта';
    }
    if ($id == USER_TYPE_ALL)
    {
        return 'Все пользователи';
    }

    return query_val_by_key('login', 'user', 'id', $id);
}

function get_group_name($id)
{
    if ($id == USER_TYPE_OWNER)
    {
        return 'Группа владельца объекта';
    }

    return query_val_by_key('name', 'group', 'id', $id);
}

function get_user_group_name($id)
{
    return get_group_name(get_user_group($id));
}

function get_current_users_group()
{
    return get_user_group($_SESSION['user_id']);
}

function get_group_prio($group, $object = NULL)
{
    if ($group == USER_TYPE_OWNER) { return get_group_prio(get_user_group(get_owner($object))); }
    return query_val_by_key('prio', 'group', 'id', $group);
}

function get_current_prio()
{
    return get_group_prio(get_current_users_group());
}

function get_user_prio($user, $object = NULL)
{
    if ($user == USER_TYPE_OWNER)
    {
        // fixme: hardcoded 1000000
        if ($object == '') { return 1000000; }

        $owner = query_val_by_key('owner', 'tree', 'id', $object);

        return get_group_prio(get_user_group($owner));
    }

    if ($user == USER_TYPE_ALL)
    {
        // fixme: hardcoded 1000000
        return 1000000;
    }

    return get_group_prio(get_user_group($user));
}

function isAdmin($user = '')
{
    if ($user == '') { $user = $_SESSION['user_id']; }

    return (get_user_prio($user) == 1);
}

function apply_default_rights($id)
{
    $default_rights = query_val_by_key('default_rights', 'object_class', 'id', query_val_by_key('class', 'tree', 'id', $id));

    $rights = explode(':', $default_rights);

    foreach ($rights as $right)
    {
        $props = explode(',', $right);

        if ($props[0] == 'u')
        {
            query_result("INSERT INTO `access` SET `object` = '$id', `user` = '$props[1]', `right` = '$props[2]', `defined` = '$props[3]';");
        } else if ($props[0] == 'g')
        {
            query_result("INSERT INTO `access` SET `object` = '$id', `group` = '$props[1]', `right` = '$props[2]', `defined` = '$props[3]';");
        }
    }
}
