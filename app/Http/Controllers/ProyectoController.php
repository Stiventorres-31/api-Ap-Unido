<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ProyectoController extends Controller
{

    public function showWithPresupuesto($codigo_proyecto){
        try {
            $proyecto = Proyecto::with(['inmuebles.presupuestos', 'inmuebles.tipo_inmueble'])
            ->where("codigo_proyecto",$codigo_proyecto)
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

            return ResponseHelper::success(200, "Proyecto obtenido", ["proyecto" => $proyectoArray]);
        } catch (Throwable $th) {
            Log::error("error al consultar un proyecto con el presupuesto " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
        }
    }

    public function showWithAsignacion($codigo_proyecto){
        try {
            $proyecto = Proyecto::with(['inmuebles.asignaciones', 'inmuebles.tipo_inmueble'])
            ->where("codigo_proyecto",$codigo_proyecto)
            ->first();

            //$presupuesto = Presupuesto::all();
           // $presupuesto = Inmueble::where("codigo_proyecto", $codigo_proyecto)->first();
           // return $proyecto;

//ME FALTA MOSTRAR SOLO LOS INMUEBLES ACTIVOS
            if (!$proyecto) {
                return ResponseHelper::error(404, "Proyecto no encontrado");
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
            $proyectos = DB::table('proyectos')
            ->leftJoin('presupuestos', 'proyectos.id', '=', 'presupuestos.proyecto_id')
            ->select(
                "proyectos.id",
                "proyectos.codigo_proyecto",
                "proyectos.departamento_proyecto",
                "proyectos.ciudad_municipio_proyecto",
                "proyectos.direccion_proyecto",
                "proyectos.user_id",
                "fecha_inicio_proyecto",
                "fecha_final_proyecto",
                "proyectos.estado",
                DB::raw('COALESCE(SUM(presupuestos.subtotal), 0) as total_presupuesto') // Si no hay presupuesto, devuelve 0
            )
            ->groupBy(
                'proyectos.codigo_proyecto',
                'proyectos.departamento_proyecto',
                'proyectos.ciudad_municipio_proyecto',
                'proyectos.direccion_proyecto',
                'proyectos.user_id',
                'proyectos.estado'
            )
            ->where("estado", "A")->paginate(2);
            return ResponseHelper::success(200, "Listado de proyectos", ["proyectos" => $proyectos]);
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
                return ResponseHelper::error(404, "No existe el proyecto");
            }
            return ResponseHelper::success(200, "Se ha encontrado", ["proyecto" => $proyecto]);
        } catch (\Throwable $th) {
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

    public function destroy($codigo_proyecto)
    {
        try {
            $proyecto = Proyecto::where("codigo_proyecto", $codigo_proyecto)->first();

            if (!$proyecto) {
                return ResponseHelper::error(404, "No se ha encontrado");
            }

            $proyecto->estado = "I";
            $proyecto->save();

            return ResponseHelper::success(200, "Se ha eliminado con exito");
        } catch (\Throwable $th) {
            Log::error("Error al eliminar un proyecto " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error", $th->getMessage()]
            );
        }
    }

    
}
