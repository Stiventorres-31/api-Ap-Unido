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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InmuebleController extends Controller
{
    public function index()
    {
        try {
            $inmuebles = Inmueble::with(["proyecto", "tipo_inmueble", "usuario"])->where("estado", "A")->get();
            return ResponseHelper::success(200, "Se ha obtenido todos los vehiculos", ["inmuebles" => $inmuebles]);
        } catch (\Throwable $th) {
            Log::error("Error al obtener todos los vehiculos " . $th->getMessage());
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

        $proyecto = Proyecto::select("id")
            ->where("codigo_proyecto", $request->codigo_proyecto)
            ->first();


        try {
            for ($i = 0; $i < $request->cantidad_inmueble; $i++) {
                Inmueble::create([
                    "proyecto_id" => $proyecto->id,
                    "tipo_inmueble_id" => intval($request->tipo_inmueble),
                    "user_id" => Auth::user()->id,
                    "estado" => "A"
                ]);
            }

            return ResponseHelper::success(200, "Se ha registrado con exito");
        } catch (\Throwable $th) {
            Log::error("Error al registrar un vehiculo " . $th->getMessage());
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
                ->with(["tipo_inmueble", "usuario", "proyecto", "presupuestos.materiale", "asignaciones.materiale"])
                ->withSum("presupuestos as total_presupuesto", "subtotal")
                //->selectRaw('COALESCE(SUM(presupuestos.subtotal), 0) as total_presupuesto')
                ->find($id);

            if (!$inmueble) {
                return ResponseHelper::error(404, "No se ha encontrado");
            }

            return ResponseHelper::success(
                200,
                "Se ha encontrado",
                ["inmueble" => $inmueble]
            );
        } catch (Throwable $th) {
            Log::error("Error al consultar un vehiculo " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }

    public function destroy(Request $request)
    {
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

            $presupuestos = Presupuesto::where("inmueble_id", $request->id)->exists();
            $asignaciones = Asignacione::where("inmueble_id", $request->id)->exists();


            if ($asignaciones) {
                return ResponseHelper::error(
                    400,
                    "No se puede eliminar este vehiculo porque tiene una asignacion"
                );
            }
            if ($presupuestos) {
                return ResponseHelper::error(
                    400,
                    "No se puede eliminar este vehiculo porque tiene un presupuesto"
                );
            }


            $inmueble->estado = "I";
            $inmueble->save();

            return ResponseHelper::success(
                200,
                "Se eliminado con exito"
            );
        } catch (Throwable $th) {
            Log::error("Error al eliminar un vehiculo " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }

    public function generarReporte($id)
    {
        $validator = Validator::make(["id" => $id], [
            "id" => "required|exists:inmuebles,id",
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $inmueble = Inmueble::with(["proyecto", "tipo_inmueble", "presupuestos.materiale.inventarios"])
                ->find($id);

            $archivoCSV = Writer::createFromString('');
            $archivoCSV->setDelimiter(",");
            $archivoCSV->setOutputBOM(Writer::BOM_UTF8);
            $archivoCSV->insertOne([
                "codigo_proyecto",
                // "inmueble_id",
                "referencia_material",
                "mombre_material",
                "costo_material",
                "Cantidad_material"
            ]);

            foreach ($inmueble->presupuestos as $presupuesto) {
                $archivoCSV->insertOne([
                    $inmueble->proyecto->codigo_proyecto,
                    // $presupuesto["inmueble_id"],
                    $presupuesto["materiale"]["referencia_material"],
                    $presupuesto["materiale"]["nombre_material"],
                    $presupuesto["costo_material"],
                    $presupuesto["cantidad_material"],
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
            Log::error("error al generar el reporte de presupuesto de un vehiculo " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }

    public function generarReportePrueba($id, Request $request)
    {

        $validator = Validator::make(["id" => $id], [
            "id" => "required|exists:inmuebles,id",
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }
        
        //ESTO ES DE PRESUPUESTO
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

        DB::reconnect();
        $reportesInmuebles = DB::table('presupuestos')
            ->join('materiales', 'presupuestos.materiale_id', '=', 'materiales.id')
            ->join('inmuebles', 'presupuestos.inmueble_id', '=', 'inmuebles.id')
            ->join('proyectos', 'presupuestos.proyecto_id', '=', 'proyectos.id')
            ->leftJoin('asignaciones', function ($join) {
                $join->on('presupuestos.inmueble_id', '=', 'asignaciones.inmueble_id')
                    ->on('presupuestos.materiale_id', '=', 'asignaciones.materiale_id');
            })
            ->where("presupuestos.inmueble_id", $id)
            ->where("inmuebles.estado", "A")
            ->orWhereDate('presupuestos.created_at','>=', $fecha_desde)
            ->orWhereDate('presupuestos.created_at','<=', $fecha_hasta)
            ->groupBy(

                'proyectos.codigo_proyecto',
                'materiales.referencia_material',
                'materiales.nombre_material',

                // 'presupuestos.costo_material',

                'presupuestos.inmueble_id',

                // 'asignaciones.costo_material',
                // 'asignaciones.cantidad_material',
                // 'asignaciones.inmueble_id'
            )
            ->select(
                "inmuebles.id",
                'proyectos.codigo_proyecto',
                'materiales.referencia_material',
                'materiales.nombre_material',

                'presupuestos.costo_material',
                'presupuestos.cantidad_material as cantidad_material_resupuesto',

                'asignaciones.cantidad_material',


                //DB::raw('SUM(presupuestos.costo_material) as costo_material_presupuesto'),
                // DB::raw('SUM(asignaciones.costo_material) as costo_material_asignado'),



                DB::raw('COALESCE(SUM(asignaciones.cantidad_material), 0) as cantidad_material_asignado'),
                // DB::raw('COALESCE(SUM(presupuestos.cantidad_material), 0) as cantidad_material_resupuesto'),
                DB::raw('(presupuestos.cantidad_material - COALESCE(SUM(asignaciones.cantidad_material), 0)) as restante'),
                DB::raw('SUM(presupuestos.subtotal) as subtotal_presupuesto'),
                DB::raw('SUM(asignaciones.subtotal) as subtotal_asignado')
            )
            ->get();



        $archivoCSV = Writer::createFromString('');
        $archivoCSV->setDelimiter(",");
        $archivoCSV->setOutputBOM(Writer::BOM_UTF8);
        $archivoCSV->insertOne([
            // "inmueble_id",
            "referencia_material",
            "nombre_material",

            // "costo_material_presupuesto",
            "Cantidad_material_presupuesto",
            "subtotal_presupuesto",

            // "costo_material_asignado",
            "Cantidad_material_asignado",
            "subtotal_asignado",
            "restante"
        ]);

        foreach ($reportesInmuebles as $reporteInmueble) {
            $archivoCSV->insertOne([
                // $reporteInmueble->inmueble_id,
                $reporteInmueble->referencia_material,
                $reporteInmueble->nombre_material,

                // $reporteInmueble->costo_material_presupuesto,
                $reporteInmueble->cantidad_material_resupuesto,
                $reporteInmueble->subtotal_presupuesto,

                // $reporteInmueble->costo_material_asignado,
                $reporteInmueble->cantidad_material_asignado,
                $reporteInmueble->subtotal_asignado,

                $reporteInmueble->restante,
            ]);
        }
        $response = new StreamedResponse(function () use ($archivoCSV) {
            echo $archivoCSV->toString();
        });

        // Establece las cabeceras adecuadas
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="reporte_presupuesto.csv"');

        return $response;
    }

    public function generarReporteAsignacion($id, Request $request)
    {
        $validator = Validator::make(["id" => $id], [
            "id" => "required|exists:inmuebles,id",
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $validatorRequest = Validator::make($request->all(), [
                "fecha_desde" => "sometimes|nullable|date",
                "fecha_hasta" => "sometimes|nullable|date|after_or_equal:fecha_desde",
            ]);

            if ($validatorRequest->fails()) {
                return ResponseHelper::error(422, $validatorRequest->errors()->first(), $validatorRequest->errors());
            }

            if ($request->filled('fecha_desde')) {
                $fecha_desde = $request->fecha_desde;
            } else {
                $fecha_desde = 0;
            }

            if ($request->filled('fecha_hasta')) {
                $fecha_hasta = $request->fecha_hasta;
            } else {
                $fecha_hasta = 0;
            }

            $inmueble = Inmueble::with(["proyecto", "tipo_inmueble", "asignaciones.materiale"])
                ->orWhereDate('asignaciones.created_at', '>=', $fecha_desde)
                ->orWhereDate('asignaciones.created_at', '<=', $fecha_hasta)
                ->find($id);


            if (!$inmueble->asignaciones) {
                return ResponseHelper::error(404, "Este inmueble no tiene asignaciones");
            }


            $archivoCSV = Writer::createFromString('');
            $archivoCSV->setDelimiter(",");
            $archivoCSV->setOutputBOM(Writer::BOM_UTF8);
            $archivoCSV->insertOne([
                "codigo_proyecto",
                // "inmueble_id",
                "referencia_material",
                "mombre_material",
                "consecutivo",
                "costo_material",
                "Cantidad_material",
                "subtotal",
                "cantidad_presupuestado",
                "fecha_asignacion",
                // "porcentaje_usado"
            ]);

            foreach ($inmueble->asignaciones as $asignacion) {
                $presupuesto = Presupuesto::select("cantidad_material")->where("inmueble_id", $id)
                    ->where("materiale_id", $asignacion->materiale_id)
                    ->first();
                $archivoCSV->insertOne([
                    $inmueble->proyecto->codigo_proyecto,
                    // $presupuesto["inmueble_id"],
                    $asignacion["materiale"]["referencia_material"],
                    $asignacion["materiale"]["nombre_material"],
                    $asignacion["consecutivo"],
                    $asignacion["costo_material"],
                    $asignacion["cantidad_material"],
                    $asignacion["subtotal"],
                    $presupuesto->cantidad_material,
                    $asignacion["created_at"],
                    // number_format(($asignacion["cantidad_material"] / $presupuesto->cantidad_material) * 100, 2)
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
            Log::error("error al generar el reporte de presupuesto de un vehiculo " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }
}
