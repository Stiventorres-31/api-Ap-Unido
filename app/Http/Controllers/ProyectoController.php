<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Asignacione;
use App\Models\Inmueble;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProyectoController extends Controller
{

    public function search($codigo_proyecto)
    {
        $validator = Validator::make(["codigo_proyecto" => $codigo_proyecto], [
            "codigo_proyecto" => "required"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            //BUSCAR LOS PROYECTO QUE COMIENCEN CON EL VALOR QUE INGRESE EL USUARIO

            // TENGO QUE MOSTRAR LA INFORMACION PRSUPUESTO Y ASIGNACION
            $proyecto = Proyecto::with(['usuario'])
                ->select('proyectos.*')

                ->withSum("presupuestos as total_presupuesto", "subtotal")
                ->leftJoin('presupuestos', 'proyectos.id', '=', 'presupuestos.proyecto_id')

                ->withSum("asignaciones as total_asignacion", "subtotal")
                ->leftJoin('asignaciones', 'proyectos.id', '=', 'asignaciones.proyecto_id')

                ->where("codigo_proyecto", "LIKE",  "%" . $codigo_proyecto . "%")
                ->where('proyectos.estado', 'A')

                ->groupBy(
                    'proyectos.id',
                    'proyectos.codigo_proyecto',
                    'proyectos.departamento_proyecto',
                    'proyectos.ciudad_municipio_proyecto',
                    'proyectos.direccion_proyecto',
                    'proyectos.user_id',
                    'proyectos.fecha_inicio_proyecto',
                    'proyectos.fecha_final_proyecto',
                    'proyectos.estado'
                )
                ->orderByDesc("id")
                ->paginate(2);

            // $proyectos = Proyecto::where("codigo_proyecto", "LIKE",  $codigo_proyecto . "%")
            //     ->where("estado", "A")
            //     ->get();

            return ResponseHelper::success(200, "Busqueda exitosa", ["proyectos" => $proyecto]);
        } catch (Throwable $th) {
            Log::error("Error al buscar un proyecto por LIKE" . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }
    public function generarReporte($codigo_proyecto, Request $request)
    {
        $validator = Validator::make(["codigo_proyecto" => $codigo_proyecto], [
            "codigo_proyecto" => "required|exists:proyectos,codigo_proyecto"
        ]);
        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }


        try {
            //PROBAR EL withCount('presupuestos');
            $proyecto = Proyecto::with([
                "presupuestos",
                "inmuebles",
                "asignaciones.materiale.inventarios",
                "asignaciones.inmueble.tipo_inmueble",
            ])
                ->where("estado", "A")
                ->where("codigo_proyecto", $codigo_proyecto)->first();

            $totalAsignado = $proyecto->asignaciones->sum("subtotal");
            // return $proyecto;
            // $totalPresupuestado = $proyecto->presupuestos->sum("subtotal");

            //return response()->json($proyecto);
            // $porcentaje_completado = round($totalPresupuestado > 0
            //     ? ($totalAsignado / $totalPresupuestado) * 100
            //     : 0);
            // return $porcentaje_completado;
            $archivoCSV = Writer::createFromString('');
            $archivoCSV->setDelimiter(",");
            $archivoCSV->setOutputBOM(Writer::BOM_UTF8);

            // $archivoCSV->insertOne([
            //     "Este proyecto lleva un " . $porcentaje_completado . "% completado"
            // ]);

            $archivoCSV->insertOne([
                "inmueble_id",
                "tipo_inmueble",
                "referencia_material",
                "mombre_material",
                // "consecutivo",
                // "costo_material",
                "Cantidad_material",
                "subtotal",
                "cantidad_presupuestado",
                "porcentaje_usado"
            ]);

            foreach ($proyecto->asignaciones as $asignacion) {

                $presupuesto = Presupuesto::select("cantidad_material")->where(
                    "inmueble_id",
                    $asignacion["inmueble_id"]
                )
                    ->where("materiale_id", $asignacion["materiale_id"])
                    ->first();

                $archivoCSV->insertOne([
                    // $proyecto->codigo_proyecto,
                    $asignacion["inmueble_id"],
                    $asignacion["inmueble"]["tipo_inmueble"]["nombre_tipo_inmueble"],
                    $asignacion["materiale"]["referencia_material"],
                    $asignacion["materiale"]["nombre_material"],
                    // $asignacion["consecutivo"],
                    // $asignacion["costo_material"],
                    $asignacion["cantidad_material"],
                    // $asignacion["subtotal"],
                    $totalAsignado,
                    $presupuesto->cantidad_material,
                    number_format(($asignacion["cantidad_material"] / $presupuesto->cantidad_material) * 100, 2)
                ]);
            }
            $response = new StreamedResponse(function () use ($archivoCSV) {
                echo $archivoCSV->toString();
            });

            // Establece las cabeceras adecuadas
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="reporte_presupuesto.csv"');

            return $response;
        } catch (Throwable $th) {
            Log::error("error al generar el reporte de presupuesto del inmueble " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }

    public function generarReportePrueba($codigo_proyecto,Request $request)
    {
        $validator = Validator::make(["codigo_proyecto" => $codigo_proyecto], [
            "codigo_proyecto" => "required|exists:proyectos,codigo_proyecto"
        ]);
        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $validatorRequest = Validator::make($request->all(),[
                "fecha_desde"=>"sometimes|nullable|date",
                "fecha_hasta"=>"sometimes|nullable|date|after_or_equal:fecha_desde",
            ]);

            if ($validatorRequest->fails()) {
                return ResponseHelper::error(422, $validatorRequest->errors()->first(), $validatorRequest->errors());
            }

            if($request->filled('fecha_desde')){
                $fecha_desde= $request->fecha_desde;
            }else{
                $fecha_desde= 0;
            }

            if($request->filled('fecha_hasta')){
                $fecha_hasta= $request->fecha_hasta;
            }else{
                $fecha_hasta= 0;
            }
            $datos = DB::table('asignaciones')
                ->join('presupuestos', function ($join) {
                    $join->on('asignaciones.inmueble_id', '=', 'presupuestos.inmueble_id')
                        ->on('asignaciones.materiale_id', '=', 'presupuestos.materiale_id');
                })
                ->join('inmuebles', 'asignaciones.inmueble_id', '=', 'inmuebles.id')
                ->join('tipo_inmuebles', 'inmuebles.tipo_inmueble_id', '=', 'tipo_inmuebles.id')
                ->join('materiales', 'asignaciones.materiale_id', '=', 'materiales.id')
                ->join('proyectos', 'asignaciones.proyecto_id', '=', 'proyectos.id')
                ->where('proyectos.codigo_proyecto', $codigo_proyecto)
                ->orWhereDate('proyectos.created_at','>=', $fecha_desde)
                ->orWhereDate('proyectos.created_at','<=', $fecha_hasta)
                ->groupBy(
                    'asignaciones.inmueble_id',
                    'proyectos.codigo_proyecto',
                    'inmuebles.tipo_inmueble_id',
                    'materiales.referencia_material',
                    'materiales.nombre_material',
                    'presupuestos.cantidad_material'
                )
                ->select(
                    'asignaciones.inmueble_id',
                    // 'inmuebles.tipo_inmueble_id',
                    'proyectos.codigo_proyecto',
                    'tipo_inmuebles.nombre_tipo_inmueble',
                    'asignaciones.proyecto_id',
                    // 'asignaciones.cantidad_material',
                    // 'asignaciones.subtotal',
                    DB::raw('SUM(asignaciones.cantidad_material) as cantidad_material'),
                    DB::raw('SUM(asignaciones.subtotal) as subtotal'),
                    'presupuestos.cantidad_material as cantidad_presupuestado',
                    'materiales.nombre_material',
                    'materiales.referencia_material',
                    DB::raw('(presupuestos.cantidad_material - COALESCE(SUM(asignaciones.cantidad_material), 0)) as restante'),

                )

                ->get();


            $archivoCSV = Writer::createFromString('');
            $archivoCSV->setDelimiter(",");
            $archivoCSV->setOutputBOM(Writer::BOM_UTF8);



            $archivoCSV->insertOne([
                "inmueble_id",
                "tipo_inmueble",
                "referencia_material",
                "mombre_material",
                // "consecutivo",
                // "costo_material",
                "Cantidad_material_asignado",
                "subtotal",
                "cantidad_presupuestado",
                "restante",
                "porcentaje_usado"
            ]);

            foreach ($datos as $key => $dato) {
                $archivoCSV->insertOne([
                    "inmueble_id" => $dato->inmueble_id,
                    "tipo_inmueble" => $dato->nombre_tipo_inmueble,
                    "referencia_material" => $dato->referencia_material,
                    "mombre_material" => $dato->nombre_material,
                    // "consecutivo",
                    // "costo_material",
                    "Cantidad_material_asignado" => $dato->cantidad_material,
                    "subtotal" => $dato->subtotal,
                    "cantidad_presupuestado" => $dato->cantidad_presupuestado,
                    "restante" => $dato->restante,
                    "porcentaje_usado" => max(0, ($dato->cantidad_material / $dato->cantidad_presupuestado) * 100)
                ]);
            }

            // $archivoCSV->insertAll($datos);

            $response = new StreamedResponse(function () use ($archivoCSV) {
                echo $archivoCSV->toString();
            });

            // Establece las cabeceras adecuadas
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="reporte_presupuesto.csv"');

            return $response;
        } catch (Throwable $th) {
            Log::error("error al generar el reporte de presupuesto del inmueble " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }
    public function showWithPresupuesto($codigo_proyecto)
    {
        try {
            $proyecto = Proyecto::with([
                'inmuebles' => function ($query) {
                    $query->with('tipo_inmueble')
                        ->where("estado", "A")
                        ->withSum('presupuestos as total_presupuesto', 'subtotal');
                },
                'inmuebles.presupuestos'
            ])
                ->where('codigo_proyecto', $codigo_proyecto)
                ->first();



            //$presupuesto = Presupuesto::all();
            // $presupuesto = Inmueble::where("codigo_proyecto", $codigo_proyecto)->first();
            //return $presupuesto;


            if (!$proyecto) {
                return ResponseHelper::error(404, "Proyecto no encontrado");
            }
            $proyectoArray = $proyecto->toArray();

            foreach ($proyectoArray['inmuebles'] as $inmueble) {
                $inmueble['total_presupuesto'] = collect($inmueble['presupuestos'] ?? [])->sum('subtotal');
                unset($inmueble['presupuestos']);
            }

            return ResponseHelper::success(200, "Taller obtenido", ["proyecto" => $proyectoArray]);
        } catch (Throwable $th) {
            Log::error("error al consultar un taller con el presupuesto " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
        }
    }

    public function showWithAsignacion($codigo_proyecto)
    {
        try {
            $proyecto = Proyecto::with([
                'inmuebles' => function ($query) {
                    $query->with(['tipo_inmueble'])
                        ->where("estado", "A")
                        ->withSum('asignaciones as total_asignacion', 'subtotal');
                },
                'inmuebles.asignaciones'
            ])
                ->where('codigo_proyecto', $codigo_proyecto)
                ->first();

            //$presupuesto = Presupuesto::all();
            // $presupuesto = Inmueble::where("codigo_proyecto", $codigo_proyecto)->first();
            // return $proyecto;

            //ME FALTA MOSTRAR SOLO LOS INMUEBLES ACTIVOS
            if (!$proyecto) {
                return ResponseHelper::error(404, "Taller no encontrado");
            }
            $proyectoArray = $proyecto->toArray();

            foreach ($proyectoArray['inmuebles'] as $inmueble) {
                $inmueble['total_asignacion'] = collect($inmueble['asignaciones'] ?? [])->sum('subtotal');
                unset($inmueble['asignaciones']);
            }

            return ResponseHelper::success(200, "Proyecto obtenido", ["proyecto" => $proyectoArray]);
        } catch (Throwable $th) {
            Log::error("error al consultar un proyecto con el presupuesto " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
        }
    }
    public function index()
    {
        try {
            // $proyectos = Proyecto::where("estado", "A")->orderBy("id","desc")->get();
            // return ResponseHelper::success(
            //     200,
            //     "Se ha obtenido todos los proyectos",
            //     ["proyectos" => $proyectos]
            // );
            // $proyectos = DB::table('proyectos')
            // ->leftJoin('presupuestos', 'proyectos.id', '=', 'presupuestos.proyecto_id')
            // ->leftJoin('users', 'proyectos.user_id', '=', 'users.id')
            // ->select(
            //     "proyectos.id",
            //     "proyectos.codigo_proyecto",
            //     "proyectos.departamento_proyecto",
            //     "proyectos.ciudad_municipio_proyecto",
            //     "proyectos.direccion_proyecto",
            //     "proyectos.user_id",
            //     "fecha_inicio_proyecto",
            //     "fecha_final_proyecto",
            //     "proyectos.estado",
            //     "users.nombre_completo as usuario",
            //     DB::raw('COALESCE(SUM(presupuestos.subtotal), 0) as total_presupuesto') // Si no hay presupuesto, devuelve 0
            // )
            // ->groupBy(
            //     'proyectos.codigo_proyecto',
            //     'proyectos.departamento_proyecto',
            //     'proyectos.ciudad_municipio_proyecto',
            //     'proyectos.direccion_proyecto',
            //     'proyectos.user_id',
            //     'proyectos.estado',
            //     "users.nombre_completo"
            // )
            // ->where("proyectos.estado", "A")->paginate(2);

            $proyectos = Proyecto::with(['usuario'])
                ->select('proyectos.*')

                ->withSum("presupuestos as total_presupuesto", "subtotal")
                ->leftJoin('presupuestos', 'proyectos.id', '=', 'presupuestos.proyecto_id')

                ->withSum("asignaciones as total_asignacion", "subtotal")
                ->leftJoin('asignaciones', 'proyectos.id', '=', 'asignaciones.proyecto_id')

                ->where('proyectos.estado', 'A')

                ->groupBy(
                    'proyectos.id',
                    'proyectos.codigo_proyecto',
                    'proyectos.departamento_proyecto',
                    'proyectos.ciudad_municipio_proyecto',
                    'proyectos.direccion_proyecto',
                    'proyectos.user_id',
                    'proyectos.fecha_inicio_proyecto',
                    'proyectos.fecha_final_proyecto',
                    'proyectos.estado'
                )->orderByDesc("id")
                ->paginate(2);

            return ResponseHelper::success(200, "Listado de talleres", ["proyectos" => $proyectos]);
        } catch (Throwable $th) {
            Log::error("Error al obtener todos los proyectos " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error", $th->getMessage()]
            );
        }
    }
    public function select()
    {
        try {
            $proyecto = Proyecto::select("id", "codigo_proyecto")->where("estado", "A")->get();
            return ResponseHelper::success(200, "Proyectos", $proyecto);
        } catch (Throwable $th) {
            Log::error("error en el select del proyecto " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "codigo_proyecto" => "required|unique:proyectos,codigo_proyecto",
            "departamento_proyecto" => "required",
            "ciudad_municipio_proyecto" => "required",
            "direccion_proyecto" => "required",
            "fecha_inicio_proyecto" => "required",
            "fecha_final_proyecto" => "required|after:fecha_inicio_proyecto",
        ]);
        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $proyectos = Proyecto::create([
                "codigo_proyecto" => strtoupper(trim($request->codigo_proyecto)),
                "departamento_proyecto" => strtoupper(trim($request->departamento_proyecto)),
                "ciudad_municipio_proyecto" => strtoupper(trim($request->ciudad_municipio_proyecto)),
                "direccion_proyecto" => strtoupper(trim($request->direccion_proyecto)),
                "fecha_inicio_proyecto" => strtoupper(trim($request->fecha_inicio_proyecto)),
                "fecha_final_proyecto" => strtoupper(trim($request->fecha_final_proyecto)),
                "estado" => "A",
                "user_id" => Auth::user()->id,
            ]);
            return ResponseHelper::success(
                201,
                "Se ha registrado con exito",
                ["proyectos" => $proyectos]
            );
        } catch (Throwable $th) {
            Log::error("Error al registrar un proyecto " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error", $th->getMessage()]
            );
        }
    }
    public function show($codigo_proyecto)
    {
        try {
            $proyecto = Proyecto::where("codigo_proyecto", $codigo_proyecto)->first();
            if (!$proyecto) {
                return ResponseHelper::error(404, "No existe el taller");
            }
            return ResponseHelper::success(200, "Se ha encontrado", ["proyecto" => $proyecto]);
        } catch (Throwable $th) {
            Log::error("Error al consultar un proyecto " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error", $th->getMessage()]
            );
        }
    }

    public function edit(Request $request, $codigo_proyecto)
    {
        $validator = Validator::make($request->all(), [
            "departamento_proyecto" => "required",
            "ciudad_municipio_proyecto" => "required",
            "direccion_proyecto" => "required",
            "fecha_inicio_proyecto" => "required",
            "fecha_final_proyecto" => "required|after:fecha_inicio_proyecto",
        ]);
        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $proyecto = Proyecto::where("estado", "A")
                ->where("codigo_proyecto", $codigo_proyecto)
                ->first();

            if (!$proyecto) {
                return ResponseHelper::error(404, "No encontrado");
            }


            $proyecto->departamento_proyecto = strtoupper(trim($request->departamento_proyecto));
            $proyecto->ciudad_municipio_proyecto = strtoupper(trim($request->ciudad_municipio_proyecto));
            $proyecto->direccion_proyecto = strtoupper(trim($request->direccion_proyecto));
            $proyecto->fecha_inicio_proyecto = strtoupper(trim($request->fecha_inicio_proyecto));
            $proyecto->fecha_final_proyecto = strtoupper(trim($request->fecha_final_proyecto));
            $proyecto->save();

            return ResponseHelper::success(
                201,
                "Se ha actualizado con exito",
                ["proyectos" => $proyecto]
            );
        } catch (\Throwable $th) {
            Log::error("Error al actualizar un proyecto " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error", $th->getMessage()]
            );
        }
    }

    public function destroy(Request $request)
    {
        try {
            $proyecto = Proyecto::where("codigo_proyecto", $request->codigo_proyecto)
                ->where("estado", "A")
                ->first();

            if (!$proyecto) {
                return ResponseHelper::error(404, "No se ha encontrado");
            }
            //SE FALTA VALIDAR SI TIENE ASIGNACIONES Y/O PRESUPUESTO ASIGNADO

            $proyecto = Proyecto::where("codigo_proyecto", $request->codigo_proyecto)
                ->where("estado", "A")
                ->first();
            $presupuestos = Presupuesto::where("proyecto_id", $proyecto->id)->exists();
            $asignaciones = Asignacione::where("proyecto_id", $proyecto->id)->exists();


            if ($asignaciones) {
                return ResponseHelper::error(
                    400,
                    "No se puede eliminar este taller porque tiene una asignacion"
                );
            }
            if ($presupuestos) {
                return ResponseHelper::error(
                    400,
                    "No se puede eliminar este taller porque tiene un presupuesto"
                );
            }
            $proyecto->estado = "I";
            $proyecto->save();

            return ResponseHelper::success(200, "Se ha eliminado con exito");
        } catch (Throwable $th) {
            Log::error("Error al eliminar un proyecto " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error", $th->getMessage()]
            );
        }
    }
}
