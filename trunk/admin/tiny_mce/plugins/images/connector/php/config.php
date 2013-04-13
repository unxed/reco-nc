<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../../../../../../globaltree/');
include('helpers.php');

//Корневая директория сайта
define('DIR_ROOT',      $_SERVER['DOCUMENT_ROOT']);
//Директория с изображениями (относительно корневой)
define('DIR_IMAGES',        $config['mce_img_folder']);
//Директория с файлами (относительно корневой)
define('DIR_FILES',     $config['mce_img_folder']);


//Высота и ширина картинки до которой будет сжато исходное изображение и создана ссылка на полную версию
define('WIDTH_TO_LINK', 500);
define('HEIGHT_TO_LINK', 500);

//Атрибуты которые будут присвоены ссылке (для скриптов типа lightbox)
define('CLASS_LINK', 'lightview');
define('REL_LINK', 'lightbox');

?>