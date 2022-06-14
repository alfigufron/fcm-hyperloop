<?php

namespace App\Utils;

use Illuminate\Support\Facades\File;

class FileSize
{
    protected static $file;
    protected static $precision;

    function __construct(){
        self::$precision = 0;
    }

    public static function getFileSize($file){
        $bytes      = File::size($file);
        $base       = log($bytes) / log(1024);
        $suffixes   = array(' bytes', ' KB', ' MB', ' GB', ' TB');

        $size = round(pow(1024, $base - floor($base)), self::$precision) . $suffixes[floor($base)];

        return $size;
    }
}
