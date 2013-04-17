<?php

include('imgtype.php');

// FIXME
if (file_exists('../config.php')) {
    // /admin, /frontend
    include('../config.php');
} else {
    // /admin/php
    include('../../config.php');
}

include('langdata.php');
include('access.php');
include('userauth.php');

$config[version] = '0.4';

appInit();

function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

function appInit()
{
    /*

    // Простейшая защита от перегрузки:
    // не будем отдавать одну и ту же страницу чаще,
    // чем раз в полсекунды на сессию

    $uri = $_SERVER['REQUEST_URI'];
    $post_hash = md5(serialize($_POST));
    $mtime = microtime_float();

    // если последнее обновление страницы было менее, чем 0.5 секунды назад,
    // вылетаем с ошибкой
    // fixme: hardcoded 0.5

    $delta = ($mtime - $_SESSION['last_access']);
    $die_flag = (($_SESSION['last_access'] != '') && ($delta < 0.5) && ($uri == $_SESSION['last_uri']) && ($post_hash == $_SESSION['last_post_hash']));

    $_SESSION['last_access'] = $mtime;
    $_SESSION['last_uri'] = $uri;
    $_SESSION['last_post_hash'] = $post_hash;

    if ($die_flag)
    {
        // fixme: hardcoded text
        die('Не стоит обновлять страницу чаще двух раз в секунду.');
    }

    */

    defineTypes();

    dbInit();
    deSlash();

    createTables();

    // fixme: this may be too slow
    maintenance();

    // todo: remove the same code from other scripts
    query_result('SET NAMES utf8');
    setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

    // writeLog('Hello, world!');
}

function dbInit()
{
    global $config;

    mysql_connect($config[db_host], $config[db_user], $config[db_pswd]) or die (mysql_error());
    mysql_select_db($config[db_base]) or die (mysql_error());

    mysql_query("SET NAMES utf8");
}

// обработка суперглобальных массивов от слешей
function deSlash()
{   
    if (ini_get('magic_quotes_gpc'))
    {
        slashes($_GET);
        slashes($_POST);
        slashes($_REQUEST);
        slashes($_COOKIE);
    }
}

function slashes(&$el)
{
    if (is_array($el))
        foreach($el as $k=>$v)
            slashes($el[$k]);
    else $el = stripslashes($el); 
}

function createTables()
{
    global $config;

    $version = getConfig('version', FALSE);
    if ($version == '')
    {
        $query = "CREATE TABLE IF NOT EXISTS config (`id` INT PRIMARY KEY AUTO_INCREMENT, `key` VARCHAR(255), `value` VARCHAR(255));";
        $sql = mysql_query($query) or die(mysql_error());

        $version = $config[version];
        setConfig('version', $version);

        $query = "CREATE TABLE IF NOT EXISTS tree (id INT PRIMARY KEY AUTO_INCREMENT, parent INT, class INT, order_token INT, name VARCHAR(255), template VARCHAR(32), `lock` CHAR(32), `last_modified` DATETIME, `modified_by` char(32));";
        $sql = mysql_query($query) or die(mysql_error());
        $query = "INSERT INTO tree (id, parent, name, class, last_modified, modified_by) VALUES (1, 0, 'Index', 1, NOW(), 1)";
        $sql = mysql_query($query);

        $query = "CREATE TABLE IF NOT EXISTS object_class (id INT PRIMARY KEY AUTO_INCREMENT, name varchar(32), table_name varchar(32), data_source varchar(32), template varchar(32));";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "INSERT INTO object_class (id, name, table_name, data_source, template) VALUES (1, 'Root', 'object_data_1', 'dfltsrc', 'default.tpl')";
        $sql = mysql_query($query);

        $query = "CREATE TABLE IF NOT EXISTS object_property (id int PRIMARY KEY AUTO_INCREMENT, name varchar(40), object_class_id int, class int, table_field varchar(32), list_children int, order_token int, is_name tinyint(1), maxcnt int, img_desc tinyint(1));";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "CREATE TABLE IF NOT EXISTS photo_gallery (id INT PRIMARY KEY AUTO_INCREMENT, object_property_id INT, width INT, height INT, class INT, prefix VARCHAR(8), crop BOOL, forceW BOOL, forceH BOOL, jpeg_quality INT);";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "CREATE TABLE IF NOT EXISTS `photo` (`id` int PRIMARY KEY AUTO_INCREMENT, `name` varchar(255), `href` varchar(255), `reference` int, `element_id` int, `order_token` int, `type` int, KEY `reference` (`reference`), KEY `reference_2` (`reference`,`element_id`));";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "CREATE TABLE IF NOT EXISTS `log` (`id` int(11) NOT NULL, `ts` timestamp, `action` int(11), `modified_by` char(32));";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "CREATE TABLE IF NOT EXISTS `session` (`user_id` int(11) NOT NULL, `session_id` char(32) NOT NULL, `ts` timestamp, tree_updated DATETIME, PRIMARY KEY (`session_id`));";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "CREATE TABLE IF NOT EXISTS `user` (`id` smallint(8) unsigned NOT NULL auto_increment, `login` varchar(50) NOT NULL default '', `password` varchar(32) NOT NULL default '', `salt` char(3) NOT NULL default '', access INT, PRIMARY KEY  (`id`), UNIQUE KEY `login` (`login`)) AUTO_INCREMENT=2;";
        $sql = mysql_query($query) or die(mysql_error());


        // последние изменения структуры базы (нужны в случае upgrade движка)

        $query = "ALTER TABLE tree ADD `lock` CHAR(32);";
        $sql = mysql_query($query);

        $query = "ALTER TABLE tree ADD `last_modified` DATETIME;";
        $sql = mysql_query($query);

        $query = "ALTER TABLE tree ADD `modified_by` char(32);";
        $sql = mysql_query($query);

        $query = "ALTER TABLE object_class ADD `add_allowed` INT(1);";
        $sql = mysql_query($query);

        $query = "ALTER TABLE object_class ADD `allowed_parents` VARCHAR(80);";
        $sql = mysql_query($query);

        $query = "ALTER TABLE object_property ADD maxcnt int;";
        $sql = mysql_query($query);

        $query = "ALTER TABLE object_property ADD img_desc tinyint(1);";
        $sql = mysql_query($query);

        $query = "ALTER TABLE user ADD access INT;";
        $sql = mysql_query($query);

        $query = "CREATE INDEX `ref` ON `photo` (`reference`);";
        $sql = mysql_query($query);

        $query = "CREATE INDEX `ref_el` ON `photo` (`reference`, `element_id`);";
        $sql = mysql_query($query);

        // introduced in 0.3

        $query = "ALTER TABLE `object_class` ADD `visible` INT(1) DEFAULT 1;";
        $sql = mysql_query($query);

        $query = "ALTER TABLE `tree` ADD `internal` VARCHAR(32);";
        $sql = mysql_query($query);

        // introduced in 0.4

        $query = "CREATE TABLE IF NOT EXISTS `group` (`id` INT PRIMARY KEY AUTO_INCREMENT, `name` varchar(32), `prio` INT);";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "ALTER TABLE `user` ADD `group` INT";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "ALTER TABLE `object_class` ADD `default_rights` VARCHAR(80);";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "ALTER TABLE `object_property` ADD `default_value` VARCHAR(80);";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "INSERT INTO `group` SET `id`='1', `name`='".win2utf('Администраторы')."',`prio`='1';";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "INSERT INTO `group` SET `id`='2', `name`='".win2utf('Зарегистрированные пользователи')."',`prio`='255';";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "CREATE TABLE IF NOT EXISTS `access` (`object` INT, `user` INT, `group` INT, `right` INT, `defined` INT);";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "CREATE INDEX `obj` ON `access` (`object`);";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "CREATE INDEX `obj_user` ON `access` (`object`, `user`);";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "CREATE INDEX `obj_group` ON `access` (`object`, `group`);";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "INSERT INTO `access` SET `object`='1', `group`='1', `right`='".access_right_all()."', `defined`='".access_right_all()."';";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "INSERT INTO `access` SET `object`='1', `user`='".USER_TYPE_OWNER."', `right`='".access_right_all()."', `defined`='".access_right_all()."';";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "INSERT INTO `access` SET `object`='1', `user`='-2', `right`='0', `defined`='".access_right_all()."';";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "ALTER TABLE tree ADD `owner` INT;";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "UPDATE `tree` SET `owner` = '1' WHERE `tree`.`id` = 1;";
        $sql = mysql_query($query) or die(mysql_error());

        // writeLog('Primary DB structure initialization finished');

    } else if ($version != $config[version])
    {
        // do something to fix database to match current version
        if ($version == '0.2')
        {
            // 0.2 -> 0.3

            $query = "ALTER TABLE `object_class` ADD `visible` INT(1) DEFAULT 1;";
            $sql = mysql_query($query);

            $query = "ALTER TABLE `tree` ADD `internal` VARCHAR(32);";
            $sql = mysql_query($query);

            // update version number

            $query = "UPDATE `config` SET `value` = '{$config['version']}' WHERE `key` = 'version';";
            $sql = mysql_query($query);

        } else if ($version == '0.3')
        {
            // 0.3 -> 0.4

            $query = "CREATE TABLE IF NOT EXISTS `group` (`id` INT PRIMARY KEY AUTO_INCREMENT, `name` varchar(32), `prio` INT);";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "ALTER TABLE `user` ADD `group` INT";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "ALTER TABLE `object_class` ADD `default_rights` VARCHAR(80);";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "ALTER TABLE `object_property` ADD `default_value` VARCHAR(80);";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "INSERT INTO `group` SET `id`='1', `name`='".win2utf('Администраторы')."',`prio`='1';";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "INSERT INTO `group` SET `id`='2', `name`='".win2utf('Зарегистрированные пользователи')."',`prio`='255';";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "CREATE TABLE IF NOT EXISTS `access` (`object` INT, `user` INT, `group` INT, `right` INT, `defined` INT);";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "CREATE INDEX `obj` ON `access` (`object`);";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "CREATE INDEX `obj_user` ON `access` (`object`, `user`);";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "CREATE INDEX `obj_group` ON `access` (`object`, `group`);";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "INSERT INTO `access` SET `object`='1', `group`='1', `right`='".access_right_all()."', `defined`='".access_right_all()."';";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "INSERT INTO `access` SET `object`='1', `user`='".USER_TYPE_OWNER."', `right`='".access_right_all()."', `defined`='".access_right_all()."';";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "INSERT INTO `access` SET `object`='1', `user`='-2', `right`='0', `defined`='".access_right_all()."';";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "UPDATE `user` SET `group` = '1' WHERE `id` = '1';";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "ALTER TABLE tree ADD `owner` INT;";
            $sql = mysql_query($query) or die(mysql_error());

            $query = "UPDATE `tree` SET `owner` = '1';";
            $sql = mysql_query($query) or die(mysql_error());

            // update version number

            $query = "UPDATE `config` SET `value` = '{$config['version']}' WHERE `key` = 'version';";
            $sql = mysql_query($query);

        } else {
            // or die
            logAndDie('DB version mismatch!');
        }
    }

    // Имя по умолчанию: admin, Пароль: 123456
    // Оталдка, отключено (debug)
    //$query = "INSERT INTO `user` (`id`, `login`, `password`, `salt`) VALUES (1, 'admin', '0c4f7127466240c6e15461f1b162328a', 'zxp');";
    //$sql = mysql_query($query);
}

function checkCreateDefaultUser()
{
    $query = "SELECT count(id) FROM user;";
    $sql = mysql_query($query);
    $row = mysql_fetch_array($sql);
    if ($row[0] == 0)
    {
        CreateDefaultUser();
    }
}

function CreateDefaultUser()
{
        $login = 'admin';
        $password = generatePassword(6, 7);
        $salt = GenerateSalt();
        $hashed_password = md5(md5($password) . $salt);

        $query = "DELETE FROM `user` WHERE `id` = '1'";
        $sql = mysql_query($query) or die(mysql_error());

        $query = "INSERT INTO `user` SET `id` = '1', `login`='{$login}', `password`='{$hashed_password}', `salt`='{$salt}', `access`='1', `group`='1'";
        $sql = mysql_query($query) or die(mysql_error());

        $myFile = getRootPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . "password.txt";
        $fh = fopen($myFile, 'w') or die("Can't open file");
        fwrite($fh, "Default login: admin\r\nDefault password: $password\r\n");
        fclose($fh);

        print "<h2>Имя пользователя и пароль для входа в систему сохранены в файле admin/password.txt (доступ только по FTP)</h2>";
}

// Функция для генерации соли, используемоей в хешировании пароля. Возращает 3 случайных символа.
function GenerateSalt($n=3)
{
    $key = '';
    $pattern = '1234567890abcdefghijklmnopqrstuvwxyz.,*_-=+';
    $counter = strlen($pattern)-1;
    for($i=0; $i<$n; $i++)
    {
        $key .= $pattern{rand(0,$counter)};
    }
    return $key;
}

// Генерация случайного пароля.
function generatePassword($length=9, $strength=0) {
    $vowels = 'aeuy';
    $consonants = 'bdghjmnpqrstvz';
    if ($strength & 1) {
        $consonants .= 'BDGHJLMNPQRSTVWXZ';
    }
    if ($strength & 2) {
        $vowels .= "AEUY";
    }
    if ($strength & 4) {
        $consonants .= '23456789';
    }
    if ($strength & 8) {
        $consonants .= '@#$%';
    }
 
    $password = '';
    $alt = time() % 2;
    for ($i = 0; $i < $length; $i++) {
        if ($alt == 1) {
            $password .= $consonants[(rand() % strlen($consonants))];
            $alt = 0;
        } else {
            $password .= $vowels[(rand() % strlen($vowels))];
            $alt = 1;
        }
    }
    return $password;
}

function getName($id)
{
    $query = "SELECT `id`, `parent`, `name`, `class`
                FROM `tree`
                WHERE `id` = '$id'";
    $sql = mysql_query($query) or die(mysql_error());
    $row = mysql_fetch_array($sql);

    $query = "SELECT `table_name` FROM `object_class` WHERE (`id` = '$row[class]')";
    $sql2 = mysql_query($query) or die(mysql_error());
    $row2 = mysql_fetch_array($sql2);
    $tableName = $row2['table_name'];

    $query = "SELECT `table_field`, `type`, `list_children` FROM `object_property` WHERE (`object_class_id` = '$row[class]') AND (`is_name` = '1')";
    $sql2 = mysql_query($query) or die(mysql_error());
    $row2 = mysql_fetch_array($sql2);
    $class = $row2['class'];
    $tableField = $row2['table_field'];

    // if (strlen($row[name]) > 32) { $row[name] = substr($row[name],0,32)."..."; }

    // если тип данного поля - ссылка, прочитаем правильное значние и попробуем определить имя раздела, на который ведет ссылка
    if ($class == 1)
    {
        $query = "SELECT `$tableField` FROM `$tableName` WHERE (`id` = '$id')";
        $sql2 = mysql_query($query) or die(mysql_error());
        $row2 = mysql_fetch_array($sql2);

        $name = getName($row2[0]);
        // добавлять '->'. для ссылок; но тогда надо и в objeditor.js при обновлении поля тоже учитывать ->
        // или в списке options выводить уже с ->? выглядит логично

        if ($name == '') { $name = 'No name'; }
    }
    else
    {
        $name = $row['name'];
    }   


    return $name;
}

function getParent($id)
{
    return query_val_by_key('parent', 'tree', 'id', $id);
}

function img_type($id)
{
    $query = "SELECT `type` FROM photo WHERE `id` = '$id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row = mysql_fetch_array($sql);

    return imgExt($row['type']);
}

function delImg($id, $not_remove_files = FALSE)
{
    // update order token

    $query = "SELECT order_token, reference, element_id FROM photo WHERE id = '$id'";
    $sql = mysql_query($query) or die(mysql_error());
    $row = mysql_fetch_array($sql);
    $order = $row['order_token'];
    $ref = $row['reference'];
    $el = $row['element_id'];

    $query = "UPDATE photo SET order_token = order_token - 1 WHERE (order_token > '$order') AND (reference = '$ref') AND (element_id = '$el')";
    $sql = mysql_query($query) or die(mysql_error());

    // save corresponding element_id

    $el = query_val_by_key('element_id', 'photo', 'id', $id);

    // actually delete

    $query = "DELETE FROM photo WHERE id = '$id'";
    $sql = mysql_query($query) or die(mysql_error());

    if (!$not_remove_files)
    {
        // original
        foreach (glob("../storage/$id.*") as $filename)
        {
                unlink($filename);
        }

        // preview
        foreach (glob("../storage/pre_$id.*") as $filename)
        {
                unlink($filename);
        }

        // other previews (их может быть много!)
        $query = "SELECT `prefix` FROM `photo_gallery` WHERE `object_property_id` = '$el'";
        $sql = mysql_query($query) or die(mysql_error());
        while ($row = mysql_fetch_array($sql))
        {
            foreach (glob('../storage/' . $row['prefix'] . $id . '.*') as $filename)
            {
                unlink($filename);
            }
        }
    }
}

// database functions

function query_result($str)
{
    if ($query = mysql_query($str))
    {
        return $query;
    } else
    {
        echo "<div style=\"position: absolute; top: 0; left: 0; color: #000; background-color: #fff; border: 1px dashed; ";
        echo "padding: 10px; margin: 10px; width=100%; height=100%;\">";
        echo "<pre><notags>";
        echo "mysql error:<br>" .mysql_error(). "<br>in query:<br>$str <br>\n";

        foreach(debug_backtrace() as $entry)
        {
                $output.="File: ".$entry['file']." (Line: ".$entry['line'].")\n"; 
                $output.="Function: ".$entry['function']."\n"; 
                $output.="Args: ".implode(", ", $entry['args'])."\n\n"; 
        } 

        print $output;

        die();
    }
}

function query_1st_row($query, $die_on_errors = TRUE)
{
    return mysql_fetch_array(query_result($query));
}

function query_val_by_key($field, $table, $key, $value, $die_on_errors = TRUE)
{
    $row = query_1st_row("SELECT `$field` FROM `$table` WHERE `$key` = '$value';");
    return $row[0];
}

// --- deprecated database functions

// from cms:

function getData($table, $field, $key, $value, $die_on_errors = TRUE)
{
    return query_val_by_key($field, $table, $key, $value, $die_on_errors);
}

// from imena

function simpleQuery($field, $table, $key, $value, $die_on_errors = TRUE)
{
    return query_val_by_key($field, $table, $key, $value, $die_on_errors);
}

function q1r($query, $die_on_errors = TRUE)
{
    return query_1st_row($query, $die_on_errors);
}

// from kis

function simpleq($param, $table, $keyfield, $value)
{
    return query_val_by_key($param, $table, $keyfield, $value);
}

function multiq($param, $table, $condition)
{
    $row = query_1st_row("SELECT `$param` FROM `$table` WHERE $condition");
    return $row[0];
}

function q($str)
{
    return query_result($str);
}

// --- config functions

function getConfig($key, $die_on_errors = TRUE)
{
    // $die_on_errors = TRUE
    // не работает! всегда TRUE.
    // поэтому пока hackfix

    $query = mysql_query("SELECT `value` FROM `config` WHERE `key` = '$key';");
    $row = mysql_fetch_array($query);
    return $row[0];

    // return query_val_by_key('value', 'config', 'key', $key, $die_on_errors);
}

function setConfig($key, $value)
{
        $id = query_val_by_key('id', 'config', 'key', $key);

        if ($id == '')
        {
                $id = insertNewId('config');

                $query = "UPDATE `config` SET `key` = '$key' WHERE `id` = '$id'";
                $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
        }

    $query = "UPDATE `config` SET `value` = '$value' WHERE `key` = '$key'";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
}

function obj_class($id)
{
    $query = "SELECT class FROM tree WHERE `id` = '$id';";
    $sql2 = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row2 = mysql_fetch_array($sql2);

    return ($row2['class']);
}

function obj_name($id)
{
    $query = "SELECT name FROM tree WHERE `id` = '$id';";
    $sql2 = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
    $row2 = mysql_fetch_array($sql2);

    return ($row2['name']);
}

function treeUpdate($id)
{
    $sid = session_id();

    $query = "UPDATE `tree` SET `last_modified` = NOW(), `modified_by` = '$sid' WHERE id = '$id';";
    $sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
}

function dropSessionWithLocks($session_id)
{
    $query = "DELETE FROM `session` WHERE `session_id` = '$session_id';";
    $sql2 = mysql_query($query) or die(mysql_error());

    $query = "UPDATE `tree` SET `lock` = NULL WHERE `lock` = '$session_id';";
    $sql2 = mysql_query($query) or die(mysql_error());
}

function destroySession($clear_saved_psw = FALSE)
{
    dropSessionWithLocks(session_id());

    if (isset($_SESSION['user_id']))
        unset($_SESSION['user_id']);

    // If it's desired to kill the session, also delete the session cookie.
    // Note: This will destroy the session, and not just the session data!
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-42000, '/');
    }

    // Finally, destroy the session.
    session_destroy();

    if ($clear_saved_psw)
    {
        setcookie('login', '', 0, "/");
        setcookie('password', '', 0, "/");
    }
}

function getLevel($tmp)
{
    $level = 0;
    while ($tmp > 0)
    {
        $query = "SELECT parent FROM tree WHERE id = '$tmp'";
        $sql = mysql_query($query) or die(mysql_error());
        $row = mysql_fetch_array($sql);
        $tmp = $row['parent'];
        $level++;
    }

    return $level;
}

function removeObject($id, $not_remove_files = FALSE, $quiet = FALSE)
{
    $query = "SELECT `id` FROM `tree` WHERE parent = '$id'";
    $sql = mysql_query($query) or die(mysql_error());
    while ($row = mysql_fetch_array($sql))
    {
        removeObject($row[0], $not_remove_files, $quiet);
    }

    if (!$quiet) { echo $id . ','; }

    // remove data

    $table = query_val_by_key('table_name', 'object_class', 'id', obj_class($id));

    $query = "DELETE FROM `$table` WHERE `id` = '$id'";
    $sql = mysql_query($query) or die(mysql_error());

    // remove photos

    $query = "SELECT `id` FROM `photo` WHERE reference = '$id'";
    $sql = mysql_query($query) or die(mysql_error());
    while ($row = mysql_fetch_array($sql))
    {
        delImg($row[0], $not_remove_files);
    }

    // remove flash & videos

    if (!$not_remove_files)
    {
        foreach (glob("../storage/$id_*.swf") as $filename)
        {
                unlink($filename);
        }

        foreach (glob("../storage/$id_*.flv") as $filename)
        {
                unlink($filename);
        }
    }

    // update order_token

    $query = "SELECT order_token, parent FROM tree WHERE id = '$id'";
    $sql = mysql_query($query) or die(mysql_error());
    $row = mysql_fetch_array($sql);
    $order = $row['order_token'];
    $parent = $row['parent'];

    $sid = session_id();

    $query = "UPDATE tree SET order_token = order_token - 1, `last_modified` = NOW(), `modified_by` = '$sid' WHERE (order_token > '$order') AND (parent = '$parent')";
    $sql = mysql_query($query) or die(mysql_error());

    $query = "DELETE FROM `tree` WHERE id = '$id'";
    $sql = mysql_query($query) or die(mysql_error());

    $query = "DELETE FROM `access` WHERE object = '$id'";
    $sql = mysql_query($query) or die(mysql_error());

    // hackfix: hardcoded action "1" - deletion
    // $query = "INSERT INTO `log` (`id`, `action`, `modified_by`) VALUES ('$id', '1', '$sid')";
    // $sql = mysql_query($query) or die(mysql_error());
}

function win2utf($s)
{
   for($i=0, $m=strlen($s); $i<$m; $i++)
   {
       $c=ord($s[$i]);
       if ($c<=127) {$t.=chr($c); continue; }
       if ($c>=192 && $c<=207)    {$t.=chr(208).chr($c-48); continue; }
       if ($c>=208 && $c<=239) {$t.=chr(208).chr($c-48); continue; }
       if ($c>=240 && $c<=255) {$t.=chr(209).chr($c-112); continue; }
       if ($c==184) { $t.=chr(209).chr(209); continue; };
   if ($c==168) { $t.=chr(208).chr(129);  continue; };
   }
   return $t;
}

// проверка $_SERVER["HTTP_HOST"] на соответствие действительности
// проверка должна выполняться при каждом входе в систему (из login.php)

function checkCorrectInstall()
{
    // проверка домена теперь делается в js, не php
    return;
}

function getRootPath()
{
        // fixme: heuristic path handling

        $dir = getcwd();
        if (preg_match('/php$/', $dir))
        {
                $dir = substr($dir, 0, strlen($dir)-4);
        }

        if (preg_match('/admin$/', $dir))
        {
                $dir = substr($dir, 0, strlen($dir)-6);
        }

        if (preg_match('/libs$/', $dir))
        {
                $dir = substr($dir, 0, strlen($dir)-5);
        }

        if (preg_match('/plugins$/', $dir))
        {
                $dir = substr($dir, 0, strlen($dir)-8);
        }

        return $dir;
}

function writeLog($string)
{
        $bt = debug_backtrace();
        $caller = array_shift($bt);

        $myFile = getRootPath() . DIRECTORY_SEPARATOR . 'cms.log';
        $fh = fopen($myFile, 'a') or die("Can't open file.");
        fwrite($fh, date(DATE_RFC822) . ', file ' . $caller['file'] . ' at line ' . $caller['line'] . ': ' . $string . "\r\n");
        fclose($fh);
}

function logAndDie($string)
{
        writeLog($string);
        die($string);
}

function insertNewId($table)
{
                $query = "SELECT MAX(`id`) + 1 FROM `$table`";
                $sql = mysql_query($query) or die(mysql_error());
                $row = mysql_fetch_array($sql);
                $id = $row[0];

                if (($id == '') || ($id == 0)) { $id = 1; }

                $query = "INSERT INTO `$table` (`id`) VALUES ('$id')";
                $sql = mysql_query($query) or die(mysql_error());
               
                return $id;
}

function maintenance()
{
    // эта функция будет вызвана при каждом открытии страницы

    // hackfix: delete orphan photos
    $query = "DELETE FROM `photo` WHERE `reference` = '';";
    $sql = mysql_query($query) or die(mysql_error());

    // delete log entires older whan 1 week
    $query = "DELETE FROM `log` WHERE (DATE_ADD(`ts`, INTERVAL 1 WEEK) < NOW());";
    $sql = mysql_query($query) or die(mysql_error());

    // clear expired sessions and their locks
    // hackfix: hardcoded 60 sec
    $query = "SELECT `session_id` FROM `session` WHERE (DATE_ADD(`session`.`ts`, INTERVAL 60 SECOND) < NOW());";
    $sql = mysql_query($query) or die(mysql_error());
    while ($row = mysql_fetch_array($sql))
    {
        dropSessionWithLocks($row[session_id]);
    }

    // удалим сессии несуществующих пользователей
    $query = "DELETE FROM `session` WHERE `user_id` NOT IN (SELECT `id` FROM `user`)";
    $sql = mysql_query($query) or die(mysql_error());
}

//
// Константы и функции для работы с поддерживаемыми типами данных.
// Добавление нового типа данных в CMS имеет смысл начинать отсюда.
//
// TODO: вынести список типов данных в базу данных,
// чтобы можно было добавлять их динамически.
//

function defineTypes()
{
    define("DATA_TYPE_LINK", 1);
    define("DATA_TYPE_TEXT", 2);
    define("DATA_TYPE_STRING", 3);
    define("DATA_TYPE_IMG_LIST", 4);
    define("DATA_TYPE_NESTED_LIST", 7);
    define("DATA_TYPE_DIV_BR", 8);
    define("DATA_TYPE_DIV_TEXTLINE", 9);
    define("DATA_TYPE_PRICE", 10);
    define("DATA_TYPE_FLASH", 11);
    define("DATA_TYPE_VIDEO", 12);
    define("DATA_TYPE_CHECKBOX_LIST", 13);
    define("DATA_TYPE_INT", 14);
    define("DATA_TYPE_BOOL", 15);
}

function getTypeName($type)
{
    switch ($type)
    {
        case DATA_TYPE_LINK:
            return 'Ссылка на другой объект';
        case DATA_TYPE_TEXT:
            return 'Текстовой блок';
        case DATA_TYPE_STRING:
            return 'Строка текста';
        case DATA_TYPE_IMG_LIST:
            return 'Галерея изображений';
        case DATA_TYPE_NESTED_LIST:
            return 'Список вложенных разделов';
        case DATA_TYPE_DIV_BR:
            return 'Разделитель: перенос строки';
        case DATA_TYPE_DIV_TEXTLINE:
            return 'Разделитель: заголовок и линия';
        case DATA_TYPE_PRICE:
            return 'Цена';
        case DATA_TYPE_FLASH:
            return 'Флеш';
        case DATA_TYPE_VIDEO:
            return 'Видео';
        case DATA_TYPE_CHECKBOX_LIST:
            return 'Выбор из группы';
        case DATA_TYPE_INT:
            return 'Целое число';
        case DATA_TYPE_BOOL:
            return 'Да/нет';
        default:
            return 'Неизвестный тип данных';
    }
}

function getTypeSQLType($type)
{
    switch ($type)
    {
        case DATA_TYPE_LINK:
            return 'INT';
        case DATA_TYPE_TEXT:
            return 'TEXT';
        case DATA_TYPE_STRING:
            return 'VARCHAR(255)';
        case DATA_TYPE_PRICE:
            return 'DECIMAL(10,2)';
        case DATA_TYPE_FLASH:
            return 'VARCHAR(11)';
        case DATA_TYPE_VIDEO:
            return 'VARCHAR(11)';
        case DATA_TYPE_CHECKBOX_LIST:
            return 'VARCHAR(255)';
        case DATA_TYPE_INT:
            return 'INT';
        case DATA_TYPE_BOOL:
            return 'BOOL';
        default:
            return;
    }
}

function listAvaliableTypes()
{
    $types = array();

    $types[] = DATA_TYPE_LINK;
    $types[] = DATA_TYPE_TEXT;
    $types[] = DATA_TYPE_STRING;
    $types[] = DATA_TYPE_IMG_LIST;
    $types[] = DATA_TYPE_NESTED_LIST;
    $types[] = DATA_TYPE_DIV_BR;
    $types[] = DATA_TYPE_DIV_TEXTLINE;
    $types[] = DATA_TYPE_PRICE;
    $types[] = DATA_TYPE_FLASH;
    $types[] = DATA_TYPE_VIDEO;
    $types[] = DATA_TYPE_CHECKBOX_LIST;
    $types[] = DATA_TYPE_INT;

    return $types;
}

function get_default_value($id, $element_id)
{
    $order = query_val_by_key('order_token', 'tree', 'id', $id);
    $result = query_val_by_key('default_value', 'object_property', 'id', $element_id);
    $result = str_replace('%n', $order, $result); // порядковый номер в группе
    $result = str_replace('%a', $id, $result); // абсолютный id
    return $result;
}

function updTs()
{
    // эта функция будет вызвана при каждом открытии страницы, но только в случае успешной авторизации

    // save session in db or update session timestamp
    $sid = session_id();

    // hackfix for reco
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $query = "INSERT INTO `session` (`user_id`, `session_id`, `ip`) VALUES ('$_SESSION[user_id]', '$sid', '$ip') ON DUPLICATE KEY UPDATE ts = NOW();";
    $sql = mysql_query($query) or die(mysql_error());
}

// secure request data
function secRD($name)
{
    if (isset($_REQUEST[$name])) { return mysql_real_escape_string($_REQUEST[$name]); }
}

function secureGetRequestData()
{
    $names = func_get_args();

    foreach ($names as $name)
    {
        if (isset($_REQUEST[$name]))
        {
            $result = mysql_real_escape_string($_REQUEST[$name]);

            $GLOBALS[$name] = $result;
        }
    }
}

function exit_with_redirect($url, $error_code = 0, $http_status = 303)
{
    if (headers_sent())
    {
        echo "<meta http-equiv=refresh content=0;url=$url><a href=$url>&rarr;</a>";

    } else {

        header('Location: '.$url, TRUE, $http_status); // 307 сохраняет данные POST, а 303 - нет
    }

    if ($error_code == 0)
    {
        exit;

    } else {

        die($error_code);
    }
}

function closeTags($text, $charset = 'utf-8')
{
    // fixme: the previous meta-charset should be stripped out

    $charsetStr = "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=$charset\">";

    // read & write DOM to avoid unclosed tags

    $oldErr = error_reporting(1);
    $doc = new DOMDocument();
    $doc->loadHTML($charsetStr.$text);
    error_reporting($oldErr);

    // remove tags except allowed ones

    $out = strip_tags($doc->saveHTML(),'<p><a><img><ul><ol><li><em><strong><span><table><tbody><tr><td><th><h1><h2><h3><h4><h5><h6><pre><address><br>');

    return $out;
} 

function admin_table_style()
{
    echo '
<style type="text/css">
/* <![CDATA[ */
table, td { border-color: gray; border-style: solid; }
table { border-width: 0 0 1px 1px; border-spacing: 0; border-collapse: collapse; }
td { margin: 0; padding: 4px; border-width: 1px 1px 0 0; }
/* ]]> */
</style>
    ';
}

?>
