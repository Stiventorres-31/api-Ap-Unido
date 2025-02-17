<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AsignacioneController extends Controller
{
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'inmueble_id' =>"required|exists:inmuebles,id",
            'proyecto_id' =>"required|exists:proyectos,id",
            'materiale_id =>"required|exists:materiales,id"',
            "consecutivo" =>"required|integer",
            'user_id' =>"required",
            'cantidad_material' =>"required",
        ]);

        // if ($validator->fails()) {
        //     return ResponseHelper::error(422,$validator->errors()->first(),);
        // }
    }
}
