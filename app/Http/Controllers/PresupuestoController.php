<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Materiale;
use App\Models\Presupuesto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PresupuestoController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "inmueble_id" => "required|exists:inmuebles,id",
            "proyecto_id" => "required|exists:proyectos,id",
            "materiales" => "required|array",

        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }
        DB::beginTransaction();
        foreach ($request->materiales as $materiale) {
            $validator = Validator::make($materiale, [
                "materiale_id" => "required|exists:materiales,id",
                "cantidad_material" => "required",
                "costo_material" => "required"
            ]);
            if ($validator->fails()) {
                DB::rollBack();
                return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
            }
            $existencia = Presupuesto::where("inmueble_id", $request->inmueble_id)
                ->where("proyecto_id", $request->proyecto_id)
                ->where("materiale_id", $materiale["materiale_id"])->exists();

            if ($existencia) {
                DB::rollback();
                $referencia_material = Materiale::select("referencia_material")->find($materiale["materiale_id"]);
                return ResponseHelper::error(
                    400,
                    "Este material '{$referencia_material["referencia_material"]}' ya existe en el presupuesto"
                );
            }
            try {
                Presupuesto::create([
                    "inmueble_id" => $request->inmueble_id,
                    "proyecto_id" => $request->proyecto_id,
                    "materiale_id" => $materiale["materiale_id"],
                    "cantidad_material" => $materiale["cantidad_material"],
                    "subtotal" => $materiale["cantidad_material"] * $materiale["costo_material"],
                    "user_id" => Auth::user()->id

                ]);
            } catch (\Throwable $th) {
                DB::rollBack();
                Log::error("Error al registrar un presupuesto " . $th->getMessage());
                return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
            }
        }
        DB::commit();
        return ResponseHelper::success(201, "Se ha registrado con exito");
    }
}
