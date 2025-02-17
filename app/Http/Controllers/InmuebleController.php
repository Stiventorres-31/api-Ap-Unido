<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Asignacione;
use App\Models\Inmueble;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InmuebleController extends Controller
{
    public function index()
    {
        try {
            $inmuebles = Inmueble::with(["proyecto","tipo_inmueble","usuario"])->where("estado", "A")->get();
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
            "codigo_proyecto" => "required|exists:proyectos,codigo_proyecto",
            "tipo_inmueble" => "required",
            "cantidad_inmueble" => "required|integer"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        $proyecto = Proyecto::select("id")->where("codigo_proyecto",$request->codigo_proyecto)->first();
        

        try {
            for ($i = 0; $i < $request->cantidad_inmueble; $i++) {
                Inmueble::create([
                    "proyecto_id" => $proyecto->id,
                    "tipo_inmueble_id" => intval($request->tipo_inmueble_id),
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
            $inmueble = Inmueble::where("estado", "A")
            ->with(["tipo_inmueble","usuario","proyecto"])
            ->find($id);
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

    public function destroy(Request $request){
        $validator = Validator::make($request->all(), [
            "id" => "required|exists:inmuebles,id"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $inmueble = Inmueble::where("estado", "A")
            ->find($request->id);

            if (!$inmueble) {
                return ResponseHelper::error(404, "No se ha encontrado");
            }

            $presupuestos = Presupuesto::where("inmueble_id",$request->inmueble_id)->exists();
            $asignaciones = Asignacione::where("inmueble_id",$request->inmueble_id)->exists();

            if($presupuestos || $asignaciones){
                return ResponseHelper::error(400, 
                "No se puede eliminar este inmueble porque tiene información relacionada");
            }


            $inmueble->estado = "I";
            $inmueble->save();

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
