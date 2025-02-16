<?php

namespace App\Helpers;

class ResponseHelper
{
    public static function success($responseCode, $message, $result = [])
    {
        return response()->json([
            'isError' => false,
            "code" => $responseCode,
            'message' => $message,
            'result' => $result
        ],$responseCode);
    }
    public static function error($responseCode, $message, $result = [])
    {
        return response()->json([
            'isError' => true,
            "code" => $responseCode,
            'message' => $message,
            'result' => $result
        ],$responseCode);
    }
}
