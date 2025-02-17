<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Inmueble;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InmuebleController extends Controller
{
    public function index()
    {
        try {
            $inmuebles = Inmueble::where("estado", "A")->get();
            return ResponseHelper::success(200, "Se ha obtenido todos los inmuebles", ["inmuebles" => $inmuebles]);
        } catch (\Throwable $th) {
            Log::error("Error al obtener todos los inmuebles " . $th->getMessage());
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
            "proyecto_id" => "required|exists:proyectos,id",
            "tipo_inmueble_id" => "required|exists:tipo_inmuebles,id",
            "cantidad_inmueble" => "required|integer"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            for ($i = 0; $i < $request->cantidad_inmueble; $i++) {
                Inmueble::create([
                    "proyecto_id" => $request->proyecto_id,
                    "tipo_inmueble_id" => $request->tipo_inmueble_id,
                    "user_id" => Auth::user()->id,
                    "estado" => "A"
                ]);
            }

            return ResponseHelper::success(200, "Se ha registrado con exito");
        } catch (\Throwable $th) {
            Log::error("Error al registrar un inmueble " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }

    public function show($id)
    {
        try {
            $inmueble = Inmueble::where("estado", "A")->with("presupuesto")->find($id);
            if (!$inmueble) {
                return ResponseHelper::error(404, "No se ha encontrado");
            }

            return ResponseHelper::success(
                200,
                "Se ha encontrado",
                ["inmueble" => $inmueble]
            );
        } catch (\Throwable $th) {
            Log::error("Error al consultar un inmueble " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }
}
