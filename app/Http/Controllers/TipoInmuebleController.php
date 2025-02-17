<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\TipoInmueble;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TipoInmuebleController extends Controller
{
    public function index()
    {
        try {
            $tipo_inmuebles = TipoInmueble::where("estado", "A")->get();

            return ResponseHelper::success(
                200,
                "Se han obtenidos todos los tipos de inmuebles",
                ["tipo_inmuebles" => $tipo_inmuebles]
            );
        } catch (\Throwable $th) {
            Log::error("Error al obtener los tipos de inmuebles " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "nombre_tipo_inmueble" => "required|unique:tipo_inmuebles,nombre_tipo_inmueble",
        ]);
        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }
        try {
            $tipo_inmueble = TipoInmueble::create([
                "nombre_tipo_inmueble" => strtoupper(trim($request->nombre_tipo_inmueble)),
                "estado" => "A",
                "user_id" => Auth::user()->id
            ]);

            return ResponseHelper::success(
                200,
                "Se ha registrado con exito",
                ["tipo_inmueble" => $tipo_inmueble]
            );
        } catch (\Throwable $th) {
            Log::error("Error al registrar un tipo de inmueble " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }



    public function show($id)
    {
        $validator = Validator::make(["id" => $id], [
            "id" => "required|exists:tipo_inmuebles,id",
        ]);
        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }
        try {
            $tipo_inmueble = TipoInmueble::with("usuario")->find($id);

            return ResponseHelper::success(
                200,
                "Se ha encontrado",
                ["tipo_inmueble" => $tipo_inmueble]
            );
        } catch (\Throwable $th) {
            Log::error("Error al registrar un tipo de inmueble " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }

    public function edit(Request $request,$id){
        $validator = Validator::make($request->all(), [
            "nombre_tipo_inmueble" => "required|unique:tipo_inmuebles,nombre_tipo_inmueble",
        ]);
        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }
        try {

            $tipo_inmueble = TipoInmueble::where("estado","A")->find($id);
            if(!$tipo_inmueble){
                return ResponseHelper::error(404,"No se ha encontrado");
            }
            $tipo_inmueble->nombre_tipo_inmueble = $request->nombre_tipo_inmueble;
            $tipo_inmueble->save();

            return ResponseHelper::success(
                200,
                "Se ha actualizado con exito",
                ["tipo_inmueble" => $tipo_inmueble]
            );
        } catch (\Throwable $th) {
            Log::error("Error al actualizar un tipo de inmueble " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }

    public function destroy($id){
        $validator = Validator::make(["id",$id], [
            "nombre_tipo_inmueble" => "required|exists:tipo_inmuebles,nombre_tipo_inmueble",
        ]);
        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }
        try {

            $tipo_inmueble = TipoInmueble::findOrFail($id);
            $tipo_inmueble->estado = "I" ;
            $tipo_inmueble->save();

            return ResponseHelper::success(
                200,
                "Se ha eliminado con exito",
                ["tipo_inmueble" => $tipo_inmueble]
            );
        } catch (\Throwable $th) {
            Log::error("Error al eliminar un tipo de inmueble " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }
}
