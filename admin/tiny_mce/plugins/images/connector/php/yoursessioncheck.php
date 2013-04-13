<?php

/**
 * Файл служит проверкой доступа по сессии,
 * вместо user подставьте ваше значение.
 * 
 * Если вы понятия не имеете о чем идет речь
 * и вам безразлична явная уязвимость в безопасности,
 * просто закомментируйте или удалите этот код.
 * 
 */

/*
if(!isset( $_SESSION['user'] )) {
    echo 'В доступе отказано, проверьте файл '.basename(__FILE__);
    exit();
}
*/

set_include_path(get_include_path() . PATH_SEPARATOR . '../../../../../../globaltree/');
include('helpers.php');

if ((isset($_FILES["Filedata"])) && (!isset($_POST['SID'])))
{
        if ($config[production])
        {
                die('Unauthorized request (1).');
        }
}

initAuth();
checkAuthPassive();

?>