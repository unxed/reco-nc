<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../../libs/globaltree/');
include('helpers.php');
initAuth();
checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('id', 'param');

// определяем элемент, родительский для данного
$query = "SELECT reference, element_id FROM photo WHERE id = '$id'";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);
$ref = $row['reference'];
$el = $row['element_id'];

if (!check_object_right($ref, ACCESS_WRITE)) { die('access denied'); }

// проверяем, нет ли среди наших "братьев" элементов с NULL в order_token (если есть - заменяем на минимальный свободный order)
$query = "SELECT id FROM photo WHERE (order_token IS NULL) AND (reference = '$ref') AND (element_id = '$el')";
$sql = mysql_query($query) or die(mysql_error());
while ($row = mysql_fetch_array($sql))
{
    $query = "SELECT MAX(order_token) FROM photo WHERE (reference = '$ref') AND (element_id = '$el')";
    $sql2 = mysql_query($query) or die(mysql_error());
    $row2 = mysql_fetch_array($sql2);
    $max = $row2[0];

    if ($max == '') { $max = 0; }
    $max++;

    $query = "UPDATE photo SET order_token = '$max' WHERE id = '$row[0]'";
    $sql2 = mysql_query($query) or die(mysql_error());
}

// получаем наш собственный order_token (он мог измениться на предыдущем шаге)
$query = "SELECT order_token FROM photo WHERE id = '$id'";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);
$order = $row['order_token'];

// определяем количество "детей" у нашего "родителя"
$query = "SELECT COUNT(id) FROM photo WHERE (reference = '$ref') AND (element_id = '$el')";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);
$cnt = $row[0];

// собственно, перемещения
if (($param == '-1') && ($order > 1))
{
    $query = "SELECT MAX(order_token) FROM photo WHERE order_token < '$order' AND (reference = '$ref') AND (element_id = '$el')";
    $sql = mysql_query($query) or die(mysql_error());
    $row = mysql_fetch_array($sql);
    $pair = $row[0];

    $query = "UPDATE photo SET order_token = order_token + 1 WHERE order_token = '$pair' AND (reference = '$ref') AND (element_id = '$el')";
    $sql = mysql_query($query) or die(mysql_error());

    $query = "UPDATE photo SET order_token = '$order' - 1 WHERE id = '$id'";
    $sql = mysql_query($query) or die(mysql_error());
}

if (($param == '1') && ($order < $cnt))
{
    $query = "SELECT MIN(order_token) FROM photo WHERE order_token > '$order' AND (reference = '$ref') AND (element_id = '$el')";
    $sql = mysql_query($query) or die(mysql_error());
    $row = mysql_fetch_array($sql);
    $pair = $row[0];

    $query = "UPDATE photo SET order_token = order_token - 1 WHERE order_token = '$pair' AND (reference = '$ref') AND (element_id = '$el')";
    $sql = mysql_query($query) or die(mysql_error());

    $query = "UPDATE photo SET order_token = '$order' + 1 WHERE id = '$id'";
    $sql = mysql_query($query) or die(mysql_error());
}

