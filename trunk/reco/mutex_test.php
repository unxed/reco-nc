<?php

include('../libs/globaltree/auth.php');
include('playhelp.php');

// контроль времени выполнения
if (CFG_LOG_EXECUTION_TIME == 1) { $start_time = microtime_float(); }

header("Content-Type: text/html; charset=utf-8");
query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

// иначе - один php скрипт в единицу времени
// вопрос: а откуда тогда вообще взялись проблемы одновременности?
// ответ прост: пользователь-то один, а сессий у него много.
// нам мьютексы как раз нужны для защиты между этими сессиями.
session_destroy();

// test code

$mid = 'ug:1234:5678'; // мьютекс user-game, закрывает доступ пользователя к игре
echo microtime(true) . ' before init<br>';
mutex_init();
echo microtime(true) . ' after init<br>';
if (mutex_acquire($mid))
{
    echo microtime(true) . ' after aquire<br>';

    sleep(2);

    echo "<br>[ok]<br>";

    echo microtime(true) . ' before release<br>';

    mutex_release($mid);

    echo microtime(true) . ' after release<br>';
} else {
    echo "mutex is closed";
}

exit;


