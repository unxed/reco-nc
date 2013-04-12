<?php

include ('../../libs/globaltree/auth.php');
checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('id');

if (!check_object_right(query_val_by_key('reference', 'photo', 'id', $id), ACCESS_WRITE)) { die('access denied'); }

delImg($id);

echo win2utf("Удалена картинка номер $id");
