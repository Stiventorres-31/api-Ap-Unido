<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index()
    {
        try {
            $users = User::where("estado", "A")->where("rol_usuario","<>","SUPER ADMIN")->get();
            return ResponseHelper::success(200, "Todos los usuarios", ["usuarios" => $users]);
        } catch (\Throwable $th) {
            Log::error("error al obtener todos los usuarios " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
        }
    }
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            "numero_identificacion" => "required|max:20|min:5|unique:users,numero_identificacion",
            "nombre_completo" => "required|string",
            'password' => "required",
            'rol_usuario' => "required|array",
            'rol_usuario.name' => "required|string|in:ADMINISTRADOR,CONSULTOR,OPERARIO"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $user = User::create([
                "numero_identificacion" => $request->numero_identificacion,
                "nombre_completo" => strtoupper(trim($request->nombre_completo)),
                "password" => $request->password,
                "rol_usuario" => strtoupper(trim($request->rol_usuario["name"])),
            ]);

            return ResponseHelper::success(200, "Se ha registrado con exito", ["usuario" => $user]);
        } catch (\Throwable $th) {
            Log::error("error al registrar un usuario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
        }
    }

    public function show($numero_identificacion)
    {
        try {
            $user = User::where("numero_identificacion", $numero_identificacion)->first();

            if (!$user) {
                return ResponseHelper::error(404, "No se ha encontrado");
            }

            return ResponseHelper::success(200, "Se ha encontrado", ["usuario" => $user]);
        } catch (\Throwable $th) {
            Log::error("error al consultar un usuario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
        }
    }

    public function edit(Request $request, $numero_identificacion)
    {
        $validator = Validator::make($request->all(), [
            "nombre_completo" => "required|string",
            "rol_usuario" => "required|array",
            "rol_usuario.name" => "required|in:ADMINISTRADOR,CONSULTOR,OPERARIO"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $user = User::where("numero_identificacion", $numero_identificacion)->first();
            if (!$user) {
                return ResponseHelper::error(404, "El usuario no existe");
            }

            $user->nombre_completo = strtoupper(trim($request->nombre_completo));
            $user->rol_usuario = strtoupper(trim($request->rol_usuario["name"]));
            $user->save();
            return Responsehelper::success(200, "Se ha actualizado con exito", ["usuario" => $user]);
        } catch (\Throwable $th) {
            Log::error("error al actualizar un usuario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
        }
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "numero_identificacion" => "required|exists:users,numero_identificacion"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $user = User::where("numero_identificacion", $request->numero_identificacion)->first();
            $user->estado = "I";
            $user->save();
            return ResponseHelper::success(200, "Se ha eliminado con exito");
        } catch (\Throwable $th) {
            Log::error("error al eliminar un usuario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
        }
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "password" => "required|string",
            "new_password" => "required|confirmed"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $user = User::find(Auth::user()->id);

            if ($user->password != $request->password) {
                return ResponseHelper::error(400, "La contraseña actual no coinciden");
            }

            $user->password = $request->new_password;
            $user->save();
            return Responsehelper::success(200, "Se ha actualizado con exito", ["usuario" => $user]);
        } catch (\Throwable $th) {
            Log::error("error al actualizar un usuario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
        }
    }
    public function changePasswordAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "numero_identificacion"=>"required|exists:users,numero_identificacion",
            "new_password" => "required|confirmed"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $user = User::where("numero_identificacion",$request->numero_identificacion)->first();

            $user->password = $request->new_password;
            $user->save();
            return Responsehelper::success(200, "Se ha actualizado con exito", ["usuario" => $user]);
        } catch (\Throwable $th) {
            Log::error("error al actualizar un usuario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
        }
    }
}
