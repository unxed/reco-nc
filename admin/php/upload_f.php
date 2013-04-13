<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../../libs/globaltree/');
include('helpers.php');
initAuth();

// Если мы работаем во флеш-режиме, и нам НЕ передали идентификатор сессии в POST,
// завершаем работу (т.к. флеш не передает id сессии в cookies, id сессии нам может из флеша придти только через POST).
// Если же нам передали идентификатор в POST, он будет использован вместо cookie, см. в начале auth.php.
if ((isset($_FILES["Filedata"])) && (!isset($_POST["PHPSESSID"])))
{
    if ($config[production])
    {
        die('Unauthorized request (1).');
    }
}

checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('ref', 'el');

if (!check_object_right($ref, ACCESS_WRITE)) { die('access denied'); }

// set maximum memory amount available for image processing

ini_set("memory_limit", $config['max_mem']);

// errors catching stuff
ini_set('display_errors', 0);
register_shutdown_function('shutdown');

// На всякий случай создадим каталог. Если он уже создан,
// сообщение об ошибки мы не увидим, поскольку воспользуемся оператором @:

@mkdir("../storage", 0777);

// Копируем файл из /tmp в uploads
// Имя файла будет таким же, как и до отправки на сервер:

if (!isset($_FILES["Filedata"]))
{ 
    $uploaded = $_FILES['imgadd']['tmp_name'];
    $path_parts = pathinfo($_FILES['imgadd']['name']);
    $swf = false;
} else
{
    $uploaded = $_FILES['Filedata']['tmp_name'];
    $path_parts = pathinfo($_FILES['Filedata']['name']);
    $swf = true;
}

// fail if no file loaded
// fixme: check $swf before writing <script>

if ($uploaded == '') { die("<script>alert('No file specified!');</script>"); }

// process image file

// copy uploaded image
$dest = '../storage/'.$ref.'_'.$el.'.swf';
unlink($dest);
copy($uploaded, $dest);
unlink($uploaded);

// force flash refresh

if (!$swf)
{
    print "
        <script language=javascript>
        top.ObjEditors[$ref].updateFlash($el);
        top.document.getElementById('id'+$ref+'element'+$el+'_w').value = '180';
        top.document.getElementById('id'+$ref+'element'+$el+'_h').value = '100';
        </script>
    ";
}



function shutdown()
{
    $isError = false;
    if ($error = error_get_last())
    {
        switch($error['type'])
        {
                    case E_ERROR:
                    case E_CORE_ERROR:
                    case E_COMPILE_ERROR:
                    case E_USER_ERROR:
                        $isError = true;
                        break;
        }
    }

    if ($isError)
    {
        global $swf;

        sendAlert("Fatal error: {$error['message']}", $swf);
    }
}

function sendAlert($msg, $swf)
{
    if ($swf)
    {
        header("HTTP/1.1 500 File Upload Error");
        echo $msg; // fixme: it seems this $msg goes nowhere, but it should rise an alert
    } else {
        print "
            <script language=javascript>
            alert('{$msg}');
            </script>
        ";
    }
}
