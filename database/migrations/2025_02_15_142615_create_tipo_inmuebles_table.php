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
        Schema::create('tipo_inmuebles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_tipo_inmueble')->unique(); 
            $table->enum("estado",["A","I",])->default("A");
            $table->foreignId('user_id')->references("id")->on("users");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_inmuebles');
    }
};
