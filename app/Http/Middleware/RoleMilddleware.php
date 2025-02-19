<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RoleMilddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        //OPERARIO SOLO TIENE PERMISO PARA REGISTRAR LAS ASIGNACIONES A LOS INMUEBLES YA CREADOS
        Log::info($request->user());
        // return response()->json($request->route()->uri());

        if ($request->user()->rol_usuario === "CONSULTOR") {
            return ResponseHelper::error(403, "No estas autorizado para esta acción");
        } else if ($request->user()->rol_usuario === "OPERARIO" && $request->route()->uri() !== "api/asignacione"){
            return ResponseHelper::error(403, "No estas autorizado para esta acción");
        }else{
            return $next($request);
        }
        
    }
}
