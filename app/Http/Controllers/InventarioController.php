<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InventarioController extends Controller
{
    public function index()
    {
        try {
            $inventario = Inventario::where("estado", "A")->get();
            return ResponseHelper::success(
                200,
                "Se ha obtenido todos los inventarios",
                ["inventario" => $inventario]
            );
        } catch (\Throwable $th) {
            Log::error("Error al obtener todos los inventarios " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor ", ["error" => $th->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "materiale_id" => "required|exists:materiales,id",
            "costo" => "required",
            "cantidad" => "required",
            "nit_proveedor" => "required",
            "nombre_proveedor" => "required",
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {

            $consecutivo = Inventario::where("materiale_id",  $request->materiale_id)
                ->max("consecutivo") ?? 0;

            $inventario = Inventario::create([

                "materiale_id" => $request->materiale_id,
                "consecutivo" => $consecutivo + 1,
                "costo" => $request->costo,
                "cantidad" => $request->cantidad,
                "nit_proveedor" => $request->nit_proveedor,
                "nombre_proveedor" => strtoupper(trim($request->nombre_proveedor)),
                "user_id" => Auth::user()->id
                
            ]);
            return ResponseHelper::success(
                200,
                "Se ha registrado con exito",
                ["inventario" => $inventario]
            );

        } catch (\Throwable $th) {
            Log::error("Error al registrar un inventario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor ", ["error" => $th->getMessage()]);
        }
    }

    public function edit(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            "costo" => "required",
            "cantidad" => "required",
            "nit_proveedor" => "required",
            "nombre_proveedor" => "required",
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {

            $inventario = Inventario::find($id);

            if (!$inventario) {
                return ResponseHelper::error(404, "No se ha encontrado");
            }

            $inventario->costo = $request->costo;
            $inventario->cantidad = $request->cantidad;
            $inventario->nit_proveedor = trim($request->nit_proveedor);
            $inventario->nombre_proveedor = strtoupper(trim($request->nombre_proveedor));

            $inventario->save();
            return ResponseHelper::success(
                200,
                "Se ha actualizado con exito",
                ["inventario" => $inventario]
            );
        } catch (\Throwable $th) {
            Log::error("Error al actualizar un inventario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor ", ["error" => $th->getMessage()]);
        }
    }

    public function show($id)
    {
        try {
            $inventario = Inventario::find($id);
            if (!$inventario) {
                return ResponseHelper::error(404, "No se ha encontrado");
            }
            return ResponseHelper::success(
                200,
                "Se ha obtenido todos los inventarios",
                ["inventario" => $inventario]
            );
        } catch (\Throwable $th) {
            Log::error("Error al obtener todos los inventarios " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor ", ["error" => $th->getMessage()]);
        }
    }
}
