<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Inmueble;
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

class PresupuestoController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "inmueble_id" => "required|exists:inmuebles,id",
            "codigo_proyecto" => "required|exists:proyectos,codigo_proyecto",
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
            $proyecto = Proyecto::where("codigo_proyecto",$request->codigo_proyecto)
            ->where("estado","A")
            ->first();
            $existencia = Presupuesto::where("inmueble_id", intval($request->inmueble_id))
                ->where("proyecto_id", $proyecto->id)
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
                    "inmueble_id" => intval($request->inmueble_id),
                    "proyecto_id" => $proyecto->id,
                    "materiale_id" => $materiale["materiale_id"],
                    "cantidad_material" => $materiale["cantidad_material"],
                    "subtotal" => $materiale["cantidad_material"] * $materiale["costo_material"],
                    "user_id" => Auth::user()->id

                ]);
            } catch (Throwable $th) {
                DB::rollBack();
                Log::error("Error al registrar un presupuesto " . $th->getMessage());
                return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
            }
        }
        DB::commit();
        return ResponseHelper::success(201, "Se ha registrado con exito");
    }

    public function fileMasivo(Request $request)
    {
        
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            "proyecto_id" => "required|exists:proyectos,id"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try{
            $cabecera = [
                "inmueble_id",
                "referencia_material",
                "costo_material",
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
    
            // Iniciar una transacción
            DB::beginTransaction();
            foreach ($archivoCSV->getRecords() as $valueCSV) {
                $validatorDataCSV = Validator::make($valueCSV, [
                    "inmueble_id" => "required",
                    "costo_material" => "required",
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
                    "cantidad_material" => "required",
    
                ]);
    
                if ($validatorDataCSV->fails()) {
                    DB::rollBack();
                    return ResponseHelper::error(422, $validatorDataCSV->errors()->first(), $validatorDataCSV->errors());
                }
    
                // $existencia_presupuesto = Presupuesto::where("inmueble_id", $valueCSV["inmueble_id"])
                //     ->where("codigo_proyecto", $request->codigo_proyecto)
                //     ->where("referencia_material", $valueCSV["referencia_material"])->exists();
    
                // if ($existencia_presupuesto) {
                //     DB::rollBack();
                //     return ResponseHelper::error(
                //         400,
                //         "El presupuesto del inmueble '{$valueCSV['inmueble_id']}' con el material '{$valueCSV['referencia_material']}' ya existe en el proyecto '{$request->codigo_proyecto}'"
                //     );
                // }
                $inmueble = Inmueble::find(trim($valueCSV["inmueble_id"]))
                    ->where("estado", "A")
                    ->first();
    
                if (!$inmueble) {
                    DB::rollBack();
                    return ResponseHelper::error(
                        400,
                        "El inmueble '{$valueCSV['inmueble_id']}' no existe"
                    );
                }

                $proyecto = Proyecto::find($request->proyecto_id)
                ->where("estado","A")
                ->first();
                if (!$proyecto) {
                    DB::rollBack();
                    return ResponseHelper::error(
                        400,
                        "El proyecto '{$valueCSV['codigo_proyecto']}' no existe"
                    );
                }
    
                if ($inmueble->proyecto_id !== $proyecto->id) {
                    DB::rollBack();
                    return ResponseHelper::error(
                        400,
                        "El inmueble '{$valueCSV['inmueble_id']}' no pertenece al proyecto '{$request->codigo_proyecto}'"
                    );
                }

                $materiale = Materiale::where("referencia_material",strtoupper(trim($valueCSV["referencia_material"])))
                ->where("estado","A")
                ->first();

                if(!$materiale){
                    DB::rollBack();
                    return ResponseHelper::error(
                        400,
                        "El material '{$valueCSV['referencia_material']}' no existe"
                    );
                }
    
    
                Presupuesto::create(
                    [
    
                        "inmueble_id" => $inmueble->id,
                        "materiale_id" => $materiale->id,
                        "costo_material" => $valueCSV["costo_material"],
                        "cantidad_material" => $valueCSV["cantidad_material"],
                        "subtotal" => ($valueCSV["costo_material"] * $valueCSV["cantidad_material"]),
                        "proyecto_id" => $proyecto->id,
                        "user_id" => auth::user()->id,
                       
                    ]
                );
    
    
          
            }
    
          
            DB::commit();
    
            return ResponseHelper::success(200, "Se ha cargado correctamente");

        }catch(Throwable $th){
            Log::error("Error al cargar el archivo CSV " . $th->getMessage());
            return ResponseHelper::error(500,"Error interno en el servidor",["error"=>$th->getMessage()]);
        }
        
    }
}
