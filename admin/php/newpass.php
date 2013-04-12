<?php

header("Content-Type: text/html; charset=utf-8");

include ('../../libs/globaltree/auth.php');
checkAuthPassive();

query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

// hackfix for reco
$login = query_val_by_key('login', 'user', 'id', $_SESSION['user_id']);
if ($login == 'stats') { die; }

secureGetRequestData('id', 'oldpass', 'param_password', 'password2');

if ((isAdmin()) && (!empty($id)))
{
    $currentUser = false;
    $oldPassText = 'Ваш пароль администратора';
    $oldPassText2 = 'пароль администратора';
} else if ((!isAdmin()) && (!empty($id)) && $id != $_SESSION['user_id'])
{
    print "Изменение чужого пароля возможно только при работе с привилегиями администратора.<br>";
    exit;
} else
{
    $id = $_SESSION['user_id'];
    $currentUser = true;
    $oldPassText = 'Старый пароль';
    $oldPassText2 = 'cтарый пароль';
}

$query = "SELECT `login` FROM `user` WHERE `id` = '$id'";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);
$name = $row['login'];

if (empty($oldpass))
{
    ?>

    <h3>Смена пароля пользователя '<?php print $name ?>'</h3>

    <form method="post" name=newpass onSubmit="if(this.param_password.value!=this.password2.value){alert('Пароль и его подтверждение не совпадают.');return(false);}">
        <table>
            <tr>
                <td><?php print $oldPassText ?>:</td>
                <td><input type="password" name="oldpass" /></td>
            </tr>
            <tr>
                <td>Новый пароль:</td>
                <td><input type="password" name="param_password" /></td>
            </tr>
            <tr>
                <td>Подтвердите новый пароль:</td>
                <td><input type="password" name="password2" /></td>
            </tr>
            <tr>
                <td></td>
                <td><input type="submit" value="Изменить пароль" /></td>
            </tr>
        </table>
        <input type=hidden name=id value="<?php print $id; ?>">
    </form>

    <?php
}
else
{
    // проверяем на наличие ошибок
    
    $error = false;
    $errort = '';

    if (strlen($oldpass) == 0)
    {
        $error = true;
        $errort .= "Вы не указали $oldPassText2.<br />";
    }

    if ($oldpass == $param_password)
    {
        $error = true;
        $errort .= "Новый пароль и $oldPassText2 совпадают.<br />";
    }

    if ($param_password != $password2)
    {
        $error = true;
        $errort .= 'Пароль и его подтверждение не совпадают.<br />';
    }
    
    if (strlen($param_password) < 6)
    {
        $error = true;
        $errort .= 'Длина нового пароля должна быть не менее 6 символов.<br />';
    }
    
    $query = "SELECT `salt` FROM `user` WHERE `id`='{$_SESSION['user_id']}' LIMIT 1";
    $sql = mysql_query($query) or die(mysql_error());
    
    if (mysql_num_rows($sql) == 1)
    {
        $row = mysql_fetch_assoc($sql);
        
        // итак, вот она соль, соответствующая этому логину:
        $salt = $row['salt'];
        
        // теперь хешируем введенный пароль как надо и повторям шаги, которые были описаны выше:
        $passwordHash = md5(md5($oldpass) . $salt);
        
        // делаем запрос к БД
        // и ищем юзера с таким логином и паролем

        $query = "SELECT `id` FROM `user` WHERE `id`='{$_SESSION['user_id']}' AND `password`='{$passwordHash}' LIMIT 1";
        $sql = mysql_query($query) or die(mysql_error());

        // если такой пользователь нашелся
        if (mysql_num_rows($sql) != 1)
        {
            $error = true;
            $errort .= "$oldPassText указан неверно.<br />";
        }
    } else {
        $error = true;
        $errort .= 'Ваша учетная запись не найдена в базе данных.<br />';
    }

    
    // если ошибок нет, то добавляем юзаре в таблицу
    
    if (!$error)
    {
        // генерируем соль и пароль
        
        $salt = GenerateSalt();
        $hashed_password = md5(md5($param_password) . $salt);
        
        $query = "UPDATE `user` SET `password`='{$hashed_password}', `salt`='{$salt}' WHERE `id`='{$id}'";
        $sql = mysql_query($query) or die(mysql_error());

        if ($id == $_SESSION['user_id']) { destroySession(TRUE); }

        print '<h4>Пароль успешно изменен.</h4>';

        if ($id == $_SESSION['user_id']) { print '<a href="login.php">Авторизоваться</a>'; }
    }
    else
    {
        print '<h4>Возникли следующие ошибки</h4>' . $errort;
    }
}

?>
