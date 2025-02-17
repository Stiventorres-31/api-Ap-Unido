<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Asignacione;
use App\Models\Inmueble;
use App\Models\Inventario;
use App\Models\Materiale;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;
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
            return ResponseHelper::error(
                422,
                $validatedData->errors()->first(),
                $validatedData->errors()
            );
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
                    return ResponseHelper::error(
                        500,
                        "Ya existe asignación del material '{$material["materiale_id"]}' con lote '{$material["consecutivo"]}'"
                    );
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
                    ->where("estado", "A")
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
            return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
        }
    }

    public function fileMasivo(Request $request,)
    {
        $validator = Validator::make($request->all(), [
            "file" => "required|file",
            "proyecto_id" => "required|exists:asignaciones,proyecto_id"
        ]);
        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        $cabecera = [
            "inmueble_id",
            "referencia_material",
            "consecutivo",
            "cantidad_material"
        ];

        $file = $request->file('file');
        $filePath = $file->getRealPath();

        $archivoCSV = Reader::createFromPath($filePath, "r");
        $archivoCSV->setDelimiter(';');;
        $archivoCSV->setHeaderOffset(0); //obtenemos la cabecera


        $archivoCabecera = $archivoCSV->getHeader();

        if ($archivoCabecera !== $cabecera) {
            return ResponseHelper::error(422, "El archivo no tiene la estructura requerida");
        }


        try {
            DB::beginTransaction();
            foreach ($archivoCSV->getRecords() as $datoAsignacionCSV) {
                $validatorDataCSV = Validator::make($datoAsignacionCSV, [
                    "inmueble_id" => "required",
                    "consecutivo" => "required|min:1",
                    "referencia_material" => [
                        "required",
                        function ($attribute, $value, $fail) {
                            $referencia_material = strtoupper($value);
                            if (!Materiale::where("referencia_material",  $referencia_material)
                                ->where("estado", "A")
                                ->exists()) {
                                $fail("La referencia del material '{$referencia_material}' no existe");
                            }
                        }
                    ],
                    "cantidad_material" => "required|numeric|min:1",
                ]);

                if ($validatorDataCSV->fails()) {
                    DB::rollBack();
                    return ResponseHelper::error(422, $validatorDataCSV->errors()->first(), $validatorDataCSV->errors());
                }

                $proyecto = Proyecto::find(trim($request->proyecto_id));
                if (!$proyecto) {
                    DB::rollback();
                    return ResponseHelper::error(404, "El proyecto no existe");
                }
                $materiale = Materiale::where("referencia_material", $datoAsignacionCSV["referencia_material"])->first();

                if (!$materiale) {
                    DB::rollback();
                    return ResponseHelper::error(
                        404,
                        "El material '{$datoAsignacionCSV["referencia_material"]}' no existe"
                    );
                }
                $presupuesto = Presupuesto::where("materiale_id", $materiale->id)
                    ->where("proyecto_id", $request->proyecto_id)
                    ->where("inmueble_id", $datoAsignacionCSV["inmueble_id"])
                    ->first();

                if (!$presupuesto) {
                    DB::rollBack();
                    return ResponseHelper::error(
                        404,
                        "No existe prespuesto para este inmueble '{$datoAsignacionCSV["inmueble_id"]}' con este material '{$materiale->referencia_material}'"
                    );
                }
                $inventario = Inventario::where("materiale_id", $presupuesto->materiale_id)
                    ->where("consecutivo", $datoAsignacionCSV["consecutivo"])->first();

                if (!$inventario) {
                    DB::rollBack();
                    return ResponseHelper::error(
                        404,
                        "No existe lote de este '{$materiale->referencia_material}' con lote '{$datoAsignacionCSV["consecutivo"]}'"
                    );
                }
                $inmueble = Inmueble::where("proyecto_id", $request->proyecto_id)
                ->find($datoAsignacionCSV["inmueble_id"]);

                if (!$inmueble) {
                    DB::rollBack();
                    return ResponseHelper::error(404, 
                    "El inmueble '{$datoAsignacionCSV["inmueble_id"]}' no existe para este proyecto '{$proyecto->codigo_proyecto}'");
                }

                //CALCULAR SI LA CANTIDAD ACTUAL Y LA ASIGNAR NO SUPERA A LA DEL PRESUPUESTO

                $cantidadAsignadoActualmente = Asignacione::where("referencia_material", $materiale->referencia_material)
                    ->where("inmueble_id", $datoAsignacionCSV["inmueble_id"])
                    ->where("consecutivo", $inventario->consecutivo)
                    ->max("cantidad_material") ?? 0;

                $calcularCantidadTotal = $cantidadAsignadoActualmente + $datoAsignacionCSV["cantidad_material"];

                if ($calcularCantidadTotal > $presupuesto->cantidad_material) {
                    DB::rollback();
                    return ResponseHelper::error(
                        400,
                        "La cantidad a asignar del material '{$materiale->referencia_material}' al inmueble '{$datoAsignacionCSV["inmueble_id"]}' supera el stock del presupuesto"
                    );
                }

                //VERIFICAR SI HAY STOCK DISPONIBLE

                if ($datoAsignacionCSV["cantidad_material"] > $inventario->cantidad) {
                    return ResponseHelper::error(422, "La cantidad del material '{$materiale->referencia_material}' a asignar super el stock del invenario");
                }

                //REALIZAR EL DESCUENTO DEL INVENTARIO



                //return "firme";

                $inventario->cantidad -= $datoAsignacionCSV["cantidad_material"];
                $inventario->save();
                // if()
                // return [
                //     "proyecto" => $proyecto,
                //     "presupuesto" => $presupuesto,
                //     "inventario" => $inventario,
                //     "inmueble" => $inmueble,
                // ];

                Asignacione::create([
                    "inmueble_id" => $datoAsignacionCSV["inmueble_id"],
                    "materiale_id" => $materiale->id,
                    "consecutivo" => $inventario->consecutivo,
                    "costo_material" => $inventario->costo,
                    "cantidad_material" => $datoAsignacionCSV["cantidad_material"],
                    "subtotal" => $inventario->costo * $datoAsignacionCSV["cantidad_material"],
                    "proyecto_id" => $proyecto->proyecto_id,
                ]);
            }

            DB::commit();
            return responseHelper::success(201, "Se han creado con exito");
        } catch (Throwable $th) {
            DB::rollback();
            Log::error("Error al registrar las asignaciones masivamente " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }
}
