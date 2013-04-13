<?php

header("Content-Type: text/html; charset=utf-8");

set_include_path(get_include_path() . PATH_SEPARATOR . '../libs/globaltree/');
include('helpers.php');
initAuth();
checkCreateDefaultUser();
checkAuth();

query_result('SET NAMES utf8');
setlocale (LC_ALL, array ('ru_RU.UTF-8', 'ru_RU.UTF-8'));

?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

<style>
body { background-color: white; }
</style>

<link rel="stylesheet" type="text/css" href="css/grid.css" />
<link rel="stylesheet" type="text/css" href="css/cms.css" />

<script type="text/javascript" src="//yandex.st/jquery/1.9.1/jquery.min.js"></script>

<script type="text/javascript" src="swfupload/swfupload.js"></script>
<script type="text/javascript" src="swfupload/swfupload.queue.js"></script>
<script type="text/javascript" src="swfupload/handlers.js"></script>
<script type="text/javascript" src="tiny_mce/tiny_mce.js"></script>
<script type="text/javascript" src="js/flowplayer-3.1.4.min.js"></script>

<script type="text/javascript" src="js/tree.js"></script>
<script type="text/javascript" src="js/objedit.js"></script>
<script type="text/javascript" src="js/online.js"></script>

</head>

<body>

<script>

$(window).on('beforeunload', function() {
    ed = getCurrentEditor();
    if (ed.changesCount > 0) {
        return 'Данные не сохранены!';
    }
});

window.onunload = function() {
    ed = getCurrentEditor();
    if (ed.changesCount > 0)
    {
        if (confirm('Сохранить объект?'))
        {
            ed.save(false);
            ed.unconditionalClose();
        }
    }

    window.ObjEditors = null;
    window.editId = null;
    window.selectedLink = null;
}   

<?php
// Set global variables for js
echo "var edit_rights = '".$config['edit_rights']."';\n";
?>

</script>

<table width="100%" border="0">
<tr>
    <td width="20%" valign="top" style="min-width: 22em;">

        <h4>
            Добро пожаловать, <?php echo $welcome; ?><br><br>
            <a class="punktir" target="_blank" href="php/users.php">Пользователи</a> <span style="color:gray;">&middot;</span>
            <a class="punktir" target="_blank" href="php/groups.php">Группы</a><br>
            <a class="punktir" target="_blank" href="php/newpass.php">Смена пароля</a> <span style="color:gray;">&middot;</span>
            <a class="punktir" href="php/login.php?logout" style="color:orange;">Выход</a><br>
        </h4>

<?php
if (!check_object_right(1, ACCESS_READ)) { die('Доступ ограничен'); }
else { echo "<div id=treeContainer></div>"; }
?>

<!--
<small>Серым цветом выводятся служебные объекты. Их можно использовать для систематизации других объектов.</small>
-->

    </td>
    <td><img src=img/t.gif height=1 width=10 style="opacity: 0;"></td>
    <td width=80% valign=top><div id=editbox></div></td>
</tr>
</table>

<script>init();</script>

<div id="classSelectionContainer">
<div id="classSelection" style="display: none;">
<select id="classList">
<?php

$query = "SELECT `id`, `name` FROM `object_class` WHERE add_allowed = 1";
$sql = mysql_query($query) or die ("MySQL error: [".mysql_error()."] in file [".__FILE__."] at line ".__LINE__);
while ($row = mysql_fetch_array($sql))
{
    // hackfix for "index" type (currently 1)
    if ($row[0] != 1) { print "<option value=$row[0]>$row[1]"; }
}

?>
</select>
<button id="addNewObject">Добавить</button>
</div>
</div>

</body>
</html>
