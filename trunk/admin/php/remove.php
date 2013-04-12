<?php

// Remove from tree

include ('../../libs/globaltree/auth.php');
checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('ids');

$idList = explode(',',$ids);

foreach ($idList as $id)
{
    if (!check_object_right(getParent($id), ACCESS_DELETE)) { die('access denied'); }
}

foreach ($idList as $id)
{
    removeObject($id);
}
