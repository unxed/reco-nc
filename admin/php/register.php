<?php

header("Content-Type: text/html; charset=utf-8");

include ('../../libs/globaltree/auth.php');
checkAuthPassive();

query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

secureGetRequestData('new_login', 'new_password', 'new_password2');

// for administrators only
if (get_current_users_group() != 1) { die('access denied'); }

if (empty($new_login))
{
    ?>
    
    <h3>Введите Ваши данные</h3>
    
    <form action="register.php" method="post" onSubmit="if(this.new_password.value!=this.new_password2.value){alert('Пароль и его подтверждение не совпадают.');return(false);}">
        <table>
            <tr>
                <td>Логин:</td>
                <td><input type="text" name="new_login" /></td>
            </tr>
            <tr>
                <td>Пароль:</td>
                <td><input type="password" name="new_password" /></td>
            </tr>
            <tr>
                <td>Пароль еще раз:</td>
                <td><input type="password" name="new_password2" /></td>
            </tr>
            <tr>
                <td></td>
                <td><input type="submit" value="Зарегистрироваться" /></td>
            </tr>
        </table>
    </form>
    
    
    <?php
}
else
{
    // проверяем на наличие ошибок (например, длина логина и пароля)

    $error = false;
    $errort = '';
    
    if (strlen($new_login) < 2)
    {
        $error = true;
        $errort .= 'Длина логина должна быть не менее 2х символов. ';
    }
// hackfix for reco
//  if (strlen($new_password) < 6)
//  {
//      $error = true;
//      $errort .= 'Длина пароля должна быть не менее 6 символов. ';
//  }
    if ($new_password != $new_password2)
    {
        $error = true;
        $errort .= 'Пароль и его подтверждение не совпадают. ';
    }
    
    // проверяем, если юзер в таблице с таким же логином
    $query = "SELECT `id` FROM `user` WHERE `login`='{$new_login}' LIMIT 1";
    $sql = mysql_query($query) or die(mysql_error());
    if (mysql_num_rows($sql)==1)
    {
        $error = true;
        $errort .= 'Пользователь с таким логином уже существует в базе данных, введите другой.<br />';
    }
    
    
    // если ошибок нет, то добавляем юзаре в таблицу
    
    if (!$error)
    {
        // генерируем соль и пароль
        
        $salt = GenerateSalt();
        $hashed_password = md5(md5($new_password) . $salt);
        
        // fixme: hardcoded 2
        $query = "INSERT INTO `user` SET `login` = '{$new_login}', `name` = '{$new_login}', `password` = '{$hashed_password}', `salt` = '{$salt}', `group` = '2';";
        $sql = mysql_query($query) or die(mysql_error());
        
        $msg = 'Пользователь зарегистрирован.';
    }
    else
    {
        $msg = 'Возникли следующие ошибки: ' . $errort;
    }

    echo "<script>alert('$msg');document.write('<meta http-equiv=refresh content=0;url=users.php>');</script><br>"; 

    exit;
}

?>
