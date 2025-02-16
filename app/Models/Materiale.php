<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materiale extends Model
{
    protected $fillable = [
        "referencia_material",
        "nombre_material",
        "user_id",
        "estado"
    ];

    public function usuario(){
        return $this->belongsTo(User::class);
    }

    public function inventarios(){
        return $this->hasMany(Inventario::class);
    }

    public function presupuestos(){
        return $this->hasMany(Presupuesto::class);
    }
    
}
