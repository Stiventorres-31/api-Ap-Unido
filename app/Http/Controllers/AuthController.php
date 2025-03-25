<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __invoke(Request $request)
    {
        $credenciales = $request->only("numero_identificacion", "password");

        try {
            if (!$token = JWTAuth::attempt($credenciales)) {
                return ResponseHelper::error(401, "Las credenciales no son correctas", []);
            }
        } catch (JWTException  $e ) {
            Log::error("error al intentar realizar el login " . $e->getMessage());
            return ResponseHelper::error(422, "No se ha podido iniciar sesion", []);

        }catch (Throwable $th){
            Log::error("error al intentar realizar el login " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }

        return ResponseHelper::success(200, "Se ha iniciado sesión con exito", [
            'token' => $token,
            'user' => Auth::user()
        ]);
    }
}
