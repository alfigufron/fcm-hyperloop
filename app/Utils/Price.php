<?php


namespace App\Utils;

class Price
{
    public static function formatted($value, $currency = 'rupiah'){
        $result = null;

        switch($currency){
            case 'rupiah':
                $result = "Rp".number_format($value, 0, ',', '.');
                break;
            default:
                break;
        }

        return $result;
    }
}
