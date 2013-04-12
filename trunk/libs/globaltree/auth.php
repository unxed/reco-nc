<?php

if (isset($_POST["PHPSESSID"])) { session_id($_POST["PHPSESSID"]); }
// upload.php uses POST for sending session id from flash uploads.
// session_start should also understand GET and POST, but if cookie available, it seems to prefer cookie

session_start();

include_once('helpers.php');

// если пользователь не авторизован

if (!isset($_SESSION['id']))
{
    // то проверяем его куки
    // вдруг там есть логин и пароль к нашему скрипту

    if (isset($_COOKIE['login']) && isset($_COOKIE['password']))
    {
        // если же такие имеются
        // то пробуем авторизовать пользователя по этим логину и паролю
        $login = mysql_real_escape_string($_COOKIE['login']);
        $password = mysql_real_escape_string($_COOKIE['password']);

        // и по аналогии с авторизацией через форму:

        // делаем запрос к БД
        // и ищем юзера с таким логином и паролем

        $query = "SELECT `id` FROM `user` WHERE `login`='{$login}' AND `password`='{$password}' LIMIT 1";
        $sql = mysql_query($query) or die(mysql_error());

        // если такой пользователь нашелся
        if (mysql_num_rows($sql) == 1)
        {
            // то мы ставим об этом метку в сессии (допустим мы будем ставить ID пользователя)

            $row = mysql_fetch_assoc($sql);
            $_SESSION['user_id'] = $row['id'];

            updTs();

            // не забываем, что для работы с сессионными данными, у нас в каждом скрипте должно присутствовать session_start();
        }
    }
}



if (isset($_SESSION['user_id']))
{
    $query = "SELECT `login` FROM `user` WHERE `id`='{$_SESSION['user_id']}' LIMIT 1";
    $sql = mysql_query($query) or die(mysql_error());
    
    // если нету такой записи с пользователем
    // ну вдруг удалили его пока он лазил по сайту.. =)
    // то надо ему убить ID, установленный в сессии, чтобы он был гостем
    if (mysql_num_rows($sql) != 1)
    {
        header('Location: login.php?logout');
        exit;
    }
    
    $row = mysql_fetch_assoc($sql);
    
    $welcome = $row['login'];
}
else
{
    $welcome = 'гость';
}

if (!$config[production])
{
    // hackfix for debugging purposes
    $_SESSION['user_id'] = '1';
    $welcome = 'admin';
}

function checkAuth($path = 'php/login.php')
{
    if (!isset($_SESSION['user_id']))
    {
        exit_with_redirect($path);
    }

    // Если в базе нет такой сессии, удалим её из cookies
    $sid = session_id();
    $row = query_1st_row("SELECT COUNT(`session_id`) FROM `session` WHERE `session_id` = '$sid'");
    if ($row[0] == 0)
    {
        destroySession();
        exit_with_redirect($path);
    }

    updTs();
}

function checkAuthPassive()
{
    if (!isset($_SESSION['user_id']))
    {
        die('Unauthorized request (0).');
    }

    // Если в базе нет такой сессии, удалим её из cookies
    $sid = session_id();
    $row = query_1st_row("SELECT COUNT(`session_id`) FROM `session` WHERE `session_id` = '$sid'");
    if ($row[0] == 0)
    {
        destroySession();
        die('Unauthorized request (0).');
    }

    updTs();
}

function authHeader($path)
{
    checkAuth('../admin/php/login.php?back='.$path);
    $welcome = query_val_by_key('login', 'user', 'id', $_SESSION['user_id']);
    echo "Добро пожаловать, $welcome! <a href=../admin/php/login.php?logout&back=$path>Выход</a>, ";
    echo "<a href=../admin/php/newpass.php>смена пароля</a><br>";
}

?>
