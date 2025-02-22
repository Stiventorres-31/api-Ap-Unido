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
            Log::error("Error al consultar un inmueble " . $th->getMessage());
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
                    "No se puede eliminar este inmueble porque tiene una asignacion"
                );
            }
            if ($presupuestos) {
                return ResponseHelper::error(
                    400,
                    "No se puede eliminar este inmueble porque tiene un presupuesto"
                );
            }


            $inmueble->estado = "I";
            $inmueble->save();

            return ResponseHelper::success(
                200,
                "Se eliminado con exito"
            );
        } catch (Throwable $th) {
            Log::error("Error al consultar un inmueble " . $th->getMessage());
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
            $archivoCSV->setDelimiter(";");
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
            Log::error("error al generar el reporte de presupuesto del inmueble " . $th->getMessage());
            return ResponseHelper::error(
                500,
                "Error interno en el servidor",
                ["error" => $th->getMessage()]
            );
        }
    }

    public function generarReporteAsignacion($id)
    {
        $validator = Validator::make(["id" => $id], [
            "id" => "required|exists:inmuebles,id",
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $inmueble = Inmueble::with(["proyecto", "tipo_inmueble", "asignaciones.materiale"])
                ->find($id);


            if (!$inmueble->asignaciones) {
                return ResponseHelper::error(404, "Este inmueble no tiene asignaciones");
            }
            // return $inmueble;

            $archivoCSV = Writer::createFromString('');
            $archivoCSV->setDelimiter(";");
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
                "porcentaje_usado"
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
                    number_format(($asignacion["cantidad_material"] / $presupuesto->cantidad_material) * 100,2)
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
}
