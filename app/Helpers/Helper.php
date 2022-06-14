<?php

namespace App\Helpers;
 
class Helper
{
    /**
     * RESPONSE HELPER
     *  
     */
    public static function response($status, $message, $data, $error){
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'error' => $error,
        ], $status);
    }

    /**
     * GET SEMESTER BY CLASS HELPER
     * 
     */
    public static function getSemesterByClass($grade) {
        $increment = 1;

        for ($i=0; $i<$grade; $i++) {
            $semester = [];
            $class = $i+1;

            if ($class === 7 || $class === 10)
                $increment = 1;

            for ($j=0; $j<2; $j++) {
                array_push($semester, $increment);
                $increment++;
            }
        }

        return $semester;
    }
}