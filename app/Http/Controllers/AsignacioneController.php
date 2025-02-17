<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Asignacione;
use App\Models\Inventario;
use App\Models\Materiale;
use App\Models\Presupuesto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AsignacioneController extends Controller
{
    public function store(Request $request)
    {

        $validatedData = Validator::make($request->all(), [
            "inmueble_id" => 'required|numeric|exists:inmuebles,id',
            "proyecto_id" => "required|numeric|exists:proyectos,id",
            "materiales" => "required|array",
        ]);

        if ($validatedData->fails()) {
            return ResponseHelper::error(422, 
            $validatedData->errors()->first(), 
            $validatedData->errors());
        }

        try {
            DB::beginTransaction();

            foreach ($request->materiales as $material) {
                $validatedData = Validator::make($material, [
                    "materiale_id"  => "required|numeric|max:10|exists:materiales,id",
                    "consecutivo" => "required|numeric",
                    "cantidad_material"    => "required|numeric|min:1"
                ]);



                if ($validatedData->fails()) {
                    DB::rollBack();
                    return ResponseHelper::error(422, $validatedData->errors()->first(), $validatedData->errors());
                }

                $existenciaAsingacion = Asignacione::where("materiale_id", strtoupper(trim($material["materiale_id"])))
                    ->where("consecutivo", $material["consecutivo"])
                    ->exists();


                if ($existenciaAsingacion) {
                    DB::rollBack();
                    return ResponseHelper::error(500, 
                    "Ya existe asignación del material '{$material["materiale_id"]}' con lote '{$material["consecutivo"]}'");
                }

                
                //VALIDAR EXISTENCIA ENTRE EL MATERIAL Y EL PRESUPUESTO DEL PROYECTO
                $datosPresupuesto = Presupuesto::where("materiale_id", strtoupper(trim($material["materiale_id"])))
                    ->where("proyecto_id", strtoupper(trim($request->proyecto_id)))->first();


                if (!$datosPresupuesto) {
                    DB::rollBack();
                    return ResponseHelper::error(422, "El material '{$material["materiale_id"]}' no pertenece al presupesto del proyecto '{$request->proyecto_id}'");
                }


                //VALIDO SI LA CANTIDAD A ASIGNAR NO SUPERA A LA CANTIDAD DEL PRESUPUESTO
                if ($datosPresupuesto->cantidad_material < $material["cantidad_material"]) {
                    DB::rollBack();
                    return ResponseHelper::error(
                        422,
                        "El material '{$material["materiale_id"]}' sobre pasa la cantidad del presupuesto"
                    );
                }
                //return $datosPresupuesto->cantidad_material;

                $estadoMaterial = Materiale::find($material["materiale_id"])
                    ->where("estado", "A");

                if (!$estadoMaterial) {
                    DB::rollBack();
                    return ResponseHelper::error(
                        404,
                        "El material '{$material["materiale_id"]}' no existe"
                    );
                }

                //OBTENGO EL INVENTARIO DE LA REFERENCIA DEL MATERIAL CON EL CONSECUTIVO
                $inventario = Inventario::where("materiale_id", $material["materiale_id"])
                    ->where("consecutivo", $material["consecutivo"])
                    ->where("estado","A")
                    ->first();

                if (!$inventario) {
                    DB::rollBack();
                    return ResponseHelper::error(404, "No se encontró inventario para el material '{$material["materiale_id"]}' con el consecutivo '{$material["consecutivo"]}'");
                }

                if ($inventario->cantidad < $material["cantidad_material"]) {
                    DB::rollBack();
                    return ResponseHelper::error(400, "No ha suficiente stock para la cantidad requerida del material '{$material["materiale_id"]}'");
                }


               // return $inventario;
                //$inventario->decrement("cantidad", 4);



                Asignacione::create([
                    "inmueble_id" => $request->inmueble_id,
                    "proyecto_id" => strtoupper($request->proyecto_id),
                    "materiale_id" => $inventario->materiale_id,
                    "costo_material" => $inventario->costo,
                    "consecutivo" => $inventario->consecutivo,
                    "subtotal" => $inventario->costo * $material["cantidad_material"],
                    "cantidad_material" => $material["cantidad_material"],
                    "user_id" => Auth::user()->id
                ]);
                DB::table('inventarios')
                    ->where("materiale_id", $material["materiale_id"])
                    ->where("consecutivo", $material["consecutivo"])
                    ->decrement("cantidad", $material["cantidad_material"]);
            }

            DB::commit();
            return ResponseHelper::success(201, "Se ha registrado con exito");
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error("Error al registrar asignaciones " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor",["error"=>$th->getMessage()]);
        }
    }
}
