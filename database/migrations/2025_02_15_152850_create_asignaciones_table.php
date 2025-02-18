<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();

            $table->decimal("costo_material");
            $table->decimal("subtotal");
            $table->decimal("cantidad_material");
            $table->bigInteger("consecutivo");
            
            $table->foreignId("inmueble_id")->references("id")->on("inmuebles");
            $table->foreignId("materiale_id")->references("id")->on("materiales");
            $table->foreignId("proyecto_id")->references("id")->on("proyectos");
            $table->foreignId("user_id")->references("id")->on( "users");
            $table->enum("estado",['A','I'])->default("A"); //Activo e Inactivo
            $table->unique(["inmueble_id","materiale_id","consecutivo"],"llave_unida");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
