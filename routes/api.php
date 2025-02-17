<?php

use App\Http\Controllers\AsignacioneController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InmuebleController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MaterialeController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\TipoInmuebleController;
use App\Http\Controllers\UserController;
use App\Models\TipoInmueble;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post("/auth/login",AuthController::class);

Route::middleware("auth:api")->group(function(){
    Route::prefix("usuario")->group(function(){
        Route::put("/changePassword",[UserController::class,"changePassword"]);
        Route::put("/changePasswordAdmin",[UserController::class,"changePasswordAdmin"]);
        Route::post("/",[UserController::class,"store"]);
        Route::get("/",[UserController::class,"index"]);
        Route::get("/{numero_identificacion}",[UserController::class,"show"]);
        Route::put("/{numero_identificacion}",[UserController::class,"edit"]);
        Route::delete("/{numero_identificacion}",[UserController::class,"destroy"]);
    });
    Route::prefix('materiale')->group(function () {
        Route::get("/", [MaterialeController::class, "index"]);
        Route::get("/{referencia_material}", [MaterialeController::class, "show"]);
        Route::post("/", [MaterialeController::class, "store"]);
        Route::put("/{referencia_material}", [MaterialeController::class, "edit"]);
        Route::delete("/", [MaterialeController::class, 'destroy']);
    });

    Route::prefix('inventario')->group(function () {
        Route::get("/", [InventarioController::class, "index"]);
        Route::get("/{id}", [InventarioController::class, "show"]);
        Route::post("/", [InventarioController::class, "store"]);
        Route::put("/{id}", [InventarioController::class, "edit"]);
        Route::delete("/", [InventarioController::class, 'destroy']);
    });

    Route::prefix('tipo_inmueble')->group(function () {
        Route::get("/", [TipoInmuebleController::class, "index"]);
        Route::get("/{id}", [TipoInmuebleController::class, "show"]);
        Route::post("/", [TipoInmuebleController::class, "store"]);
        Route::put("/{id}", [TipoInmuebleController::class, "edit"]);
        Route::delete("/", [TipoInmuebleController::class, 'destroy']);
    });

    Route::prefix('proyecto')->group(function () {
        Route::get("/", [ProyectoController::class, "index"]);
        Route::get("/select", [ProyectoController::class, "select"]);
        Route::get("/{codigo_proyecto}", [ProyectoController::class, "show"]);
        Route::post("/", [ProyectoController::class, "store"]);
        Route::put("/{codigo_proyecto}", [ProyectoController::class, "edit"]);
        Route::get("/presupuesto/{codigo_proyecto}", [ProyectoController::class, "showWithPresupuesto"]);
        Route::get("/asignacion/{codigo_proyecto}", [ProyectoController::class, "showWithAsignacion"]);
        Route::delete("/", [ProyectoController::class, 'destroy']);
    });

    Route::prefix('inmueble')->group(function () {
        Route::get("/", [InmuebleController::class, "index"]);
        Route::get("/{id}", [InmuebleController::class, "show"]);
        Route::post("/", [InmuebleController::class, "store"]);
        Route::get("/report/{id}", [InmuebleController::class, "generarReporte"]);
        // Route::put("/{id}", [InmuebleController::class, "edit"]);
        Route::delete("/", [InmuebleController::class, 'destroy']);

    });

    Route::prefix('presupuesto')->group(function () {
        // Route::get("/", [PresupuestoController::class, "index"]);
        // Route::get("/{id}", [PresupuestoController::class, "show"]);
        Route::post("/", [PresupuestoController::class, "store"]);
        Route::post("/file", [PresupuestoController::class, "fileMasivo"]);
        // Route::put("/{id}", [PresupuestoController::class, "edit"]);
        // Route::delete("/", [PresupuestoController::class, 'destroy']);
    });

    Route::prefix('asignacion')->group(function () {
        // Route::get("/", [PresupuestoController::class, "index"]);
        // Route::get("/{id}", [PresupuestoController::class, "show"]);
        Route::post("/", [AsignacioneController::class, "store"]);
        Route::post("/file", [AsignacioneController::class, "fileMasivo"]);
        // Route::put("/{id}", [PresupuestoController::class, "edit"]);
        // Route::delete("/", [PresupuestoController::class, 'destroy']);
    });
});

