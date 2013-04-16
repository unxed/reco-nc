<?php

$config = array();

// ---------------- Configuration start ----------------

// -------- Basic options. Edit this to match your configuration.
// -------- Базовые настройки. Отредактируйте их в соответствии с вашей конфигурацией.

// mysql connection properties
// настройки соединения с mysql

$config['db_host'] = 'localhost';
$config['db_user'] = 'username';
$config['db_pswd'] = 'password';
$config['db_base'] = 'database';

// default language
// язык по умолчанию

$config['language'] = 'ru';

// -------- Advanced options (do not change if not sure).
// -------- Дополнительные настройки. Не изменяйте их, если не уверены в том, что вы делаете.

// Enable users to edit access rights?
// Разрешать ли пользователям редактировать права доступа к объектам движка?

$config['edit_rights'] = true;

// Images upload properties (cms)
// Настройки загрузчика изображений

// max memory size
// ограничение используемой памяти

// 45M enough for max 8,00 mpx
// 46M enough for max 8,78 mpx (3604x2436)
// 47M enough for max 8,90 mpx (3624x2456)
// 64M enough for max 12.72 mpx (4368x2912)
// tested on windows xp sp3 pro, celeron 2,66, 4 (3,71) Gb ram, xampp

$config['max_mem'] = '80M';

// large side for width and height (for scaling uploaded images)

// вертикальные картинки ограничиваем в 680 по высоте (800 не влазит), чтобы влезали на экран, горизонтальные - в 1024 по той же причине
// таким образом, вертикальные фотки у нас будут несколько меньше горизонтальных, но зато будут помещаться на экране при 1280x1024.
// итого: горизонтальные стараемся привести к 1024x768, вертикальные - к 680x451.

$config['large_side_w'] = '1024';
$config['large_side_h'] = '680';

// default video size
// размеры видеокадра по умолчанию

$config['video_w'] = '320';
$config['video_h'] = '240';

// tinyMCE image upload properties
// настройки загрузчика картинок редактора tinyMCE

$config['mce_img_folder'] = '/storage_mce';

// ---------------- Configuration end ----------------

$GLOBALS['config'] = $config;

