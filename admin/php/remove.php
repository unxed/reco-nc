<?php

// Remove from tree

set_include_path(get_include_path() . PATH_SEPARATOR . '../../libs/globaltree/');
include('helpers.php');
initAuth();
checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('ids');

$idList = explode(',',$ids);

foreach ($idList as $id)
{
    if (!check_object_right(getParent($id), ACCESS_DELETE)) { die('access denied'); }
}

// FIXME: проверять также право на удаление для каждого потомка.

foreach ($idList as $id)
{
    removeObject($id);
}
