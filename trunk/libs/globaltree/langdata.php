<?php

$langs = array();

// RU

$lang = array();
$lang['default_title'] = 'Без названия';

$langs['ru'] = $lang;

// EN

$lang = array();
$lang['default_title'] = 'Untitled';

$langs['en'] = $lang;

// end

$langdata = $langs[$config['language']];

$GLOBALS['langdata'] = $langdata;

