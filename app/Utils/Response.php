<?php

namespace App\Utils;

class Response
{
    protected static $format;
    protected static $status;
    protected static $code;

    function __construct(){
        self::$format = 'json';
    }

    public static function status($status){
        self::$status = $status;
        return new self;
    }

    public static function code($code){
        self::$code = $code;
        return new self;
    }

    public function raw(){
        self::$format = 'raw';
        return $this;
    }

    public function result($data = []){
        $status = self::$status;
        $format = self::$format;

        if ($format == 'raw') {
            return self::$status($data);
        }else{
            return response()->json(self::$status($data));
        }
    }

    public function success($data = []){
        $status = self::$code;

        $response = [
            'status' => ($status ? $status : 200),
            'message' => 'success',
            'data' => $data,
            'error' => []
        ];
        return $response;
    }

    public function failure($error = []){
        if(!is_array($error)){
            $error = [
                $error
            ];
        }

        $status = self::$code;

        $response = [
            'status' => ($status ? $status : 422),
            'message' => 'failure',
            'data' => [],
            'error' => $error
        ];
        return $response;
    }
}
