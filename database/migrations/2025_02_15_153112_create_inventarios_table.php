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
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("consecutivo");
            $table->decimal("costo");
            $table->string("cantidad");
            $table->string("nit_proveedor");
            $table->string("nombre_proveedor");
            //$table->string("descripcion_proveedor")->nullable();
            $table->enum("estado",["A","I"])->default("A");
            $table->foreignId("user_id")->references("id")->on("users");
            $table->foreignId("materiale_id")->references("id")->on("materiales");

            $table->unique(["materiale_id","consecutivo"]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
