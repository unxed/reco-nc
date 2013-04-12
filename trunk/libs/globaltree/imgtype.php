<?php

define("IMAGETYPE_GIF", 1);
define("IMAGETYPE_JPEG", 2);
define("IMAGETYPE_PNG", 3);
define("IMAGETYPE_SWF", 4);
define("IMAGETYPE_PSD", 5);
define("IMAGETYPE_BMP", 6);
define("IMAGETYPE_TIFF_II", 7); // (intel byte order)
define("IMAGETYPE_TIFF_MM", 8); // (motorola byte order)
define("IMAGETYPE_JPC", 9);
define("IMAGETYPE_JP2", 10);
define("IMAGETYPE_JPX", 11);
define("IMAGETYPE_JB2", 12);
define("IMAGETYPE_SWC", 13);
define("IMAGETYPE_IFF", 14);
define("IMAGETYPE_WBMP", 15);
define("IMAGETYPE_XBM", 16);


function imagetypefrommime($mime)
{
    switch ($mime)
    {
        case 'image/jpeg': return IMAGETYPE_JPEG;
        case 'image/gif': return IMAGETYPE_GIF;
        case 'image/png': return IMAGETYPE_PNG;
    }
}

function imagetypefromimagefile($path)
{
    $size = getimagesize($path);
    return imagetypefrommime($size['mime']);
}

function imgExt($type)
{
    switch ($type)
    {
        case IMAGETYPE_GIF:
            $ext = 'gif';
            break;
        case IMAGETYPE_JPEG:
            $ext = 'jpg';
            break;
        case IMAGETYPE_PNG:
            $ext = 'png';
            break;
    }

    return $ext;
}

