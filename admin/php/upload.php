<?php

include ('../../libs/globaltree/auth.php');

// Если мы работаем во флеш-режиме, и нам НЕ передали идентификатор сессии в POST,
// завершаем работу (т.к. флеш не передает id сессии в cookies, id сессии нам может из флеша придти только через POST).
// Если же нам передали идентификатор в POST, он будет использован вместо cookie, см. в начале auth.php.
if ((isset($_FILES["Filedata"])) && (!isset($_POST["PHPSESSID"])))
{
    if ($config[production])
    {
        die('Unauthorized request (1).');
    }
}

checkAuthPassive();

header("Content-Type: text/html; charset=utf-8");

secureGetRequestData('ref', 'el');

if (!check_object_right($ref, ACCESS_WRITE)) { die('access denied'); }

// set maximum memory amount available for image processing

ini_set("memory_limit", $config['max_mem']);

// errors catching stuff
ini_set('display_errors', 0);
register_shutdown_function('shutdown');

// На всякий случай создадим каталог. Если он уже создан,
// сообщение об ошибки мы не увидим, поскольку воспользуемся оператором @:

@mkdir("../storage", 0777);

// Копируем файл из /tmp в uploads
// Имя файла будет таким же, как и до отправки на сервер:

if (!isset($_FILES["Filedata"]))
{ 
    $uploaded = $_FILES['imgadd']['tmp_name'];
    $path_parts = pathinfo($_FILES['imgadd']['name']);
    $swf = false;
} else
{
    $uploaded = $_FILES['Filedata']['tmp_name'];
    $path_parts = pathinfo($_FILES['Filedata']['name']);
    $swf = true;
}

$type = imagetypefromimagefile($uploaded);

if ($type == '')
{
    $extension = $path_parts[extension];

    if ($extension != '')
    {
        $msg = "Unsupported file type: $extension";
        sendAlert($msg, $swf);
    } // else: no file specified - no action

    exit();
}

// get next available id from database

$query = "SELECT MAX(id) + 1 FROM `photo`";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);
$id = $row[0];

if (($id == '') || ($id == 0)) { $id = 1; }

// process image file

// read uploaded image
$img = getImgFromFile($uploaded);

// scale down

if ($img[width] > $img[height]) { $largeSide = $config['large_side_w']; } else { $largeSide = $config['large_side_h']; }

// hackfix: 1280x640 - bacground image - shouldn't be resized
if ( ( ($img[height] > $largeSide) || ($img[width] > $largeSide) ) && (!( ($img[width] == 1280) && ($img[height] == 630) )) )
{
    $scaled = imgScaleDownByLargeSide($img, $largeSide);
    imagedestroy($img[image]);
    $img = $scaled;

    // save scaled down (or original itself if no scale was done) as original
    writePreview($id, $img, '', '', $type, '');
}
else
{
    // save the original image
    copy($uploaded,"../storage/".$id.".".imgExt(imagetypefromimagefile($uploaded)));
}

// save previews

// preview for admin panel
writePreview($id, $img, 180, 100, IMAGETYPE_JPEG, 'pre_', '', '', '', '');

$query = "SELECT width, height, type, prefix, crop, forceW, forceH, jpeg_quality FROM photo_gallery WHERE object_property_id = '$el';";
$sql = mysql_query($query) or die(mysql_error());
while ($row = mysql_fetch_array($sql))
{
    writePreview($id, $img, $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7]);
}

// destroy uploaded image handler
imagedestroy($img[image]);

// update DB

$query = "SELECT max(order_token) FROM `photo` WHERE (`reference` = '$ref') AND (element_id = '$el');";
$sql = mysql_query($query) or die(mysql_error());
$row = mysql_fetch_array($sql);
$maxOrder = $row[0]; if ($maxOrder == '') { $maxOrder = 0; }
$maxOrder++;

$query = "INSERT INTO photo (id, reference, type, element_id, order_token) VALUES ('$id', '$ref', '$type', '$el', '$maxOrder');";
if (!($sql = mysql_query($query)))
{
    die(mysql_error());
}

// force images refresh

if (!$swf)
{
    print "
        <script language=javascript>
        top.ObjEditors[$ref].updateImages($el);
        </script>
    ";
}

function writePreview($id, $img, $width, $height, $type, $prefix, $crop, $forceW, $forceH, $jpeg_quality)
{
    if ($jpeg_quality == '') { $jpeg_quality = 75; }

    if (($width == '') || ($width == 0)) { $width = $img[width]; }
    if (($height == '') || ($height == 0)) { $height = $img[height]; }

    $filename = "../storage/$prefix".$id.".".imgExt($type);
    $modified = false;

    if (($img[width] == $width) && ($img[height] == $height))
    {
        $dimg = $img[image];
    }
    else
    {
        $dimg = imgToSizeByVerticalCrop($img, $width, $height, $crop, $forceW, $forceH);
        $modified = true;
    }

    switch ($type)
    {
        case IMAGETYPE_GIF:
            imagegif($dimg, $filename);
            break;
        case IMAGETYPE_JPEG:
            imagejpeg($dimg, $filename, $jpeg_quality);
            break;
        case IMAGETYPE_PNG:
            imagepng($dimg, $filename);
            break;
        default:
            die('error: unknown image format');
    }

    if ($modified)
    {
        imagedestroy($dimg);
    }
}

function getImgFromFile($imgFile)
{
    $type = imagetypefromimagefile($imgFile);
    switch ($type)
    {
        case IMAGETYPE_GIF:
            $src = imagecreatefromgif($imgFile);
            break;
        case IMAGETYPE_JPEG:
            $src = imagecreatefromjpeg($imgFile);
            break;
        case IMAGETYPE_PNG:
            $src = imagecreatefrompng($imgFile);
            break;
        default:
            die('error: unknown image format');
    }
    if (!imagealphablending($src, false)) { die("<script>alert('imagealphablending(0) failed');</script>"); }
    if (!imagesavealpha($src, true)) { die("<script>alert('imagesavealpha(1) failed');</script>"); }

    list($width,$height)=getimagesize($imgFile);

    return array("image" => $src, "width" => $width, "height" => $height);
}

function imgScaleDownByLargeSide($img, $largeSide)
{
    $src = $img[image];
    $height = $img[height];
    $width = $img[width];

    if ( $height > $width )
    {
        // scale down by height (width: proportional)

        if ($height > $largeSide) { $newheight = $largeSide; } else { $newheight = $height; }
        $newwidth=($width/$height)*$newheight;
    }
    else
    {
        if ($width > $largeSide) { $newwidth = $largeSide; } else { $newwidth = $width; }
        $newheight=($height/$width)*$newwidth;
    }

    $tmp=imagecreatetruecolor($newwidth,$newheight);
    if (!imagealphablending($tmp, false)) { die("<script>alert('imagealphablending(0) failed');</script>"); }
    if (!imagesavealpha($tmp, true)) { die("<script>alert('imagesavealpha(1) failed');</script>"); }

    imagecopyresampled($tmp,$src,0,0,0,0,$newwidth,$newheight,$width,$height);

    //die("<script>alert('imagecopyresampled($tmp,$src,0,0,0,0,$newwidth,$newheight,$width,$height);');</script>");

    return array("image" => $tmp, "width" => $newwidth, "height" => $newheight);
}

function imgToSizeByVerticalCrop($img, $nw, $nh, $crop, $forceW, $forceH)
{
    if ($crop == '') { $crop = true; }
    if ($forceW == '') { $forceW = false; }
    if ($forceH == '') { $forceH = false; }

    // hackfix
    if (($forceH) && ($forceW)) { $forceH = false; $forceW = false; }

    $src = $img[image];
    $height = $img[height];
    $width = $img[width];

    if ($nw == 0) { $nw = $width; }
    if ($nh == 0) { $nh = $height; }

    $ratio = $width/$height;
    $newratio = $nw/$nh;

    // Resize & Crop

    // ----- Resize

    // Если изображение в пропорциях шире формата,
    if ((($ratio > $newratio) || ($forceH)) & (!($forceW)))
    {
        // Изменяем его высоту до форматной, пропорционально уменьшая ширину
        $newheight=$nh;
        $newwidth=($width/$height)*$newheight;
    }
    else
    {
        // Иначе изменяем его ширину до форматной, пропорционально уменьшая высоту
        $newwidth=$nw;
        $newheight=($height/$width)*$newwidth;
    }

    $tmp=imagecreatetruecolor($newwidth,$newheight);
    if (!imagealphablending($tmp, false)) { die("<script>alert('imagealphablending(0) failed');</script>"); }
    if (!imagesavealpha($tmp, true)) { die("<script>alert('imagesavealpha(1) failed');</script>"); }

    imagecopyresampled($tmp,$src,0,0,0,0,$newwidth,$newheight,$width,$height);

    $w = $newwidth;
    $h = $newheight;

    if ($crop)
    {

        // ----- Crop
        // Обрезаем изображение по формату. Таким образом, используется 100% полезной площади формата,
        // при этом при превышении ширины обрезается ширина (равномерно справа и слева),
        // а при превышении высоты - высота (также равномерно).

        $dimg = imagecreatetruecolor($nw, $nh);
        if (!imagealphablending($dimg, false)) { die("<script>alert('imagealphablending(0) failed');</script>"); }
        if (!imagesavealpha($dimg, true)) { die("<script>alert('imagesavealpha(1) failed');</script>"); }

        $wm = $w/$nw;
        $hm = $h/$nh;
 
        $h_height = $nh/2;
        $w_height = $nw/2;
 
        if ($ratio > $newratio) {
 
            $adjusted_width = $w / $hm;
            $half_width = $adjusted_width / 2;
            $int_width = $half_width - $w_height;
 
            imagecopyresampled($dimg,$tmp,-$int_width,0,0,0,$adjusted_width,$nh,$w,$h);
 
        } else {
 
            $adjusted_height = $h / $wm;
            $half_height = $adjusted_height / 2;
            $int_height = $half_height - $h_height;
 
            imagecopyresampled($dimg,$tmp,0,-$int_height,0,0,$nw,$adjusted_height,$w,$h);
        }


        /*
        // this code does letterboxing at standard 4x3 files
 
        $wm = $w/$nw;
        $hm = $h/$nh;
 
        $h_height = $nh/2;
        $w_height = $nw/2;
 
        if($w> $h) {
 
            $adjusted_width = $w / $hm;
            $half_width = $adjusted_width / 2;
            $int_width = $half_width - $w_height;
 
            imagecopyresampled($dimg,$tmp,-$int_width,0,0,0,$adjusted_width,$nh,$w,$h);
 
        } elseif(($w <$h) || ($w == $h)) {
 
            $adjusted_height = $h / $wm;
            $half_height = $adjusted_height / 2;
            $int_height = $half_height - $h_height;
 
            imagecopyresampled($dimg,$tmp,0,-$int_height,0,0,$nw,$adjusted_height,$w,$h);
 
        } else {
            imagecopyresampled($dimg,$tmp,0,0,0,0,$nw,$nh,$w,$h);
        }

        */

        imagedestroy($tmp);
    }
    else
    {
        $dimg = $tmp;
    }

    return $dimg;
}

function shutdown()
{
    $isError = false;
    if ($error = error_get_last())
    {
        switch($error['type'])
        {
                    case E_ERROR:
                    case E_CORE_ERROR:
                    case E_COMPILE_ERROR:
                    case E_USER_ERROR:
                        $isError = true;
                        break;
        }
    }

    if ($isError)
    {
        global $swf;

        sendAlert("Fatal error: {$error['message']}", $swf);
    }
}

function sendAlert($msg, $swf)
{
    if ($swf)
    {
        header("HTTP/1.1 500 File Upload Error");
        echo $msg; // fixme: it seems this $msg goes nowhere, but it should rise an alert
    } else {
        print "
            <script language=javascript>
            alert('{$msg}');
            </script>
        ";
    }
}
