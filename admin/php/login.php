<?php

header("Content-Type: text/html; charset=utf-8");

session_start();
set_include_path(get_include_path() . PATH_SEPARATOR . '../../libs/globaltree/');
include('helpers.php');

query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

secureGetRequestData('back', 'login', 'logout', 'password', 'remember');

$err = '';

if ($back == '')
{
    $back = '../index.php';
} else { $back = $back; }

if (isset($logout))
{
    destroySession(TRUE);

    // и переносим его на главную
    header("Location: $back");
    exit;
}

if (isset($_SESSION['user_id']))
{
    // юзер уже залогинен, перекидываем его отсюда на закрытую страницу

    header("Location: $back");
    exit;

}

if (!empty($login))
{
    $query = "SELECT `salt` FROM `user` WHERE `login`='{$login}' LIMIT 1";
    $sql = mysql_query($query) or die(mysql_error());
    
    if (mysql_num_rows($sql) == 1)
    {
        $row = mysql_fetch_assoc($sql);
        
        // итак, вот она соль, соответствующая этому логину:
        $salt = $row['salt'];
        
        // теперь хешируем введенный пароль как надо и повторям шаги, которые были описаны выше:
        $password_real = md5(md5($password) . $salt);
        
        // и пошло поехало...

        // делаем запрос к БД
        // и ищем юзера с таким логином и паролем

        $query = "SELECT `id` FROM `user` WHERE `login`='{$login}' AND `password`='{$password_real}'    LIMIT 1";
        $sql = mysql_query($query) or die(mysql_error());

        // если такой пользователь нашелся
        if (mysql_num_rows($sql) == 1)
        {
            // то мы ставим об этом метку в сессии (допустим мы будем ставить ID пользователя)

            $row = mysql_fetch_assoc($sql);
            $_SESSION['user_id'] = $row['id'];

            updTs();
            
            // если пользователь решил "запомнить себя"
            // то ставим ему в куку логин с хешем пароля
            
            $time = 86400; // ставим куку на 24 часа
            
            if (isset($remember))
            {
                setcookie('login', $login, time()+$time, "/");
                setcookie('password', $password_real, time()+$time, "/");
            }
            
            // и перекидываем его на закрытую страницу
            header("Location: $back");
            exit;

            // не забываем, что для работы с сессионными данными, у нас в каждом скрипте должно присутствовать session_start();
        }
        else
        {
            // die('Неверная комбинация имени и пароля. <a href="login.php">Попробовать ещё раз</a>');

                        $err = 'Неверная комбинация имени и пароля.';
        }
    }
    else
    {
        // die('Неверная комбинация имени и пароля. <a href="login.php">Попробовать ещё раз</a>');

                $err = 'Неверная комбинация имени и пароля.';
    }
}

// hackfix for reco

print '<!-- <style>body,td{font-family: Verdana, Tahoma, Arial; font-size:12pt;}</style> -->';

include('../../frontend/preset.php');
if (CFG_ALLOW_ADDITIONAL_DESIGN == 1)
{
    // make some style
    echo '<link rel="stylesheet" href="../../frontend/templates/stylesheet.css" type="text/css" charset="utf-8" />';
}

print '
<style>
body, td, input { font-size: 18pt; font-family: Verdana, Tahoma, Arial, Sans Serif; } 
</style>
<form action="login.php" method="post">
<table width=100% height=100%><tr><td align=center>
    <table style="width: 17.4em;">
        <tr>
            <td>Логин</td>
            <td align=right><input type="text" name="login" /></td>
        </tr>
        <tr>
            <td>Пароль</td>
            <td align=right><input type="password" name="password" /></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="checkbox" name="remember" />запомнить</td>
        </tr>
        <tr>
            <td colspan=2 align=center>';
// hackfix for reco
print '<input type=submit value=Вход>';
print '<br><br><a href=?login=stats&password=stats&back='.$back.'>Статистика</a>';

// <input type="image" src="../btn/auth.gif" /></td>
print '
        </tr>
                <tr>
                        <td colspan=2 align=center><div><div style="height: 30px; padding-top: 10px; color: red; font-weight: bold;">
';
print $err;
print "
                        </div></td>
                </tr>
    </table>
</td></tr></table>
<input type=hidden name=back value=$back>
</form>
";

?>