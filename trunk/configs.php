<?php

$config = array();

// configuration itself

$config['path'] = '/';

$config['db_host'] = 'localhost';
$config['db_user'] = 'root';
$config['db_pswd'] = '12345';
$config['db_base'] = 'reco';

// default language

$config['language'] = 'ru';

// images upload properties (cms)

// max memory size

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

$config['video_w'] = '320';
$config['video_h'] = '240';

// tinyMCE image upload properties

$config['mce_img_folder'] = '/storage_mce';

// production mode (auth enabled)

$config['production'] = true;

// Разрешать ли пользователям редактировать права доступа?

$config['edit_rights'] = true;

// configuration end

$GLOBALS['config'] = $config;

