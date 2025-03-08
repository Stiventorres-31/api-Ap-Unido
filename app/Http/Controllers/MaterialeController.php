<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Inventario;
use App\Models\Materiale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MaterialeController extends Controller
{
    public function index()
    {
        try {
            $materiales = DB::table('materiales')
            ->leftJoin('inventarios', 'materiales.id', '=', 'inventarios.materiale_id')
            ->where('materiales.estado','A')
            ->select(
                'materiales.id',
                'materiales.referencia_material',
                'materiales.nombre_material',
                DB::raw('COALESCE(SUM(inventarios.cantidad), 0) as cantidad_total_de_material'),
                'materiales.estado'
            )
            ->groupBy(
                'materiales.id',
                'materiales.referencia_material',
                'materiales.nombre_material',
                'materiales.estado'
            )
            ->orderByDesc('cantidad_total_de_material')
            ->get();
            return ResponseHelper::success(
                200,
                "Se ha obtenido todos los materiales",
                ["materiales" => $materiales]
            );
        } catch (\Throwable $th) {
            Log::error("Error al obtener todos los materiales " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor ", ["error" => $th->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "referencia_material" => "required|unique:materiales,referencia_material",
            "nombre_material" => "required",
            "costo" => "required",
            "cantidad" => "required",
            "nit_proveedor" => "required",
            "nombre_proveedor" => "required"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            

            $materiale = Materiale::create([
                "referencia_material" => strtoupper(trim($request->referencia_material)),
                "nombre_material" => strtoupper(trim($request->nombre_material)),
                "user_id" => Auth::user()->id
            ]);

            $consecutivoActual = Inventario::where("materiale_id", $materiale->id)->max("consecutivo");
            // return $consecutivoActual;
            $inventario = Inventario::create([
                "materiale_id" => $materiale->id,
                "consecutivo" => $consecutivoActual + 1,
                "costo" => $request->costo,
                "cantidad" => $request->cantidad,
                "nit_proveedor" => $request->nit_proveedor,
                "nombre_proveedor" => strtoupper(trim($request->nombre_proveedor)),
                "user_id"=>Auth::user()->id
            ]);
            $inventario->save();
            return ResponseHelper::success(200, "Se ha registrado con exito", ["materiale" => $materiale]);
        } catch (\Throwable $th) {
            Log::error("Error al registrar un material " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor ", ["error" => $th->getMessage()]);
        }
    }

    public function edit(Request $request, $referencia_material)
    {
        $validator = Validator::make($request->all(), [
            "nombre_material" => "required"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }
        try {
            $materiale = Materiale::where("referencia_material", $referencia_material)->first();
            if (!$materiale) {
                return ResponseHelper::error(404, "Material no encontrado");
            }
            $materiale->nombre_material = strtoupper(trim($request->nombre_material));
            $materiale->save();

            return ResponseHelper::success(200, "Se ha actualizado con exito", ["materiale" => $materiale]);
        } catch (\Throwable $th) {
            Log::error("Error al actualizar un material " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor ", ["error" => $th->getMessage()]);
        }
    }

    public function show($referencia_material)
    {
        $validator = Validator::make(["referencia_material" => $referencia_material], [
            "referencia_material" => "required|exists:materiales,referencia_material"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }
        try {
            $materiale = Materiale::with(['inventarios' => function ($query) {
                $query->where("estado", "A");
            }])
            ->where('referencia_material', $referencia_material)
            ->first();
            

            return ResponseHelper::success(200, "Se ha encontrado", ["materiale" => $materiale]);
        } catch (\Throwable $th) {
            Log::error("Error al consultar un material " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor ", ["error" => $th->getMessage()]);
        }
    }



    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "referencia_material" => "required|exists:materiales,referencia_material"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }


        try {
            $materiale = Materiale::where("referencia_material", $request->referencia_material)->first();

            //TENGO QUE VALIDAR SI EXISTE EL MATERIAL EN UNA ASIGNACION Y/O PRESPUESTO PARA PDOERLO ELIMINAR
            $materiale->estado = "I";
            $materiale->save();
            return ResponseHelper::success(200, "Se ha eliminado con exito");
        } catch (\Throwable $th) {
            Log::error("Error al eliminar un material " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor ", ["error" => $th->getMessage()]);
        }
    }
}
