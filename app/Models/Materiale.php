<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materiale extends Model
{
    use HasFactory;
    protected $fillable = [
        "referencia_material",
        "nombre_material",
        "user_id",
        "estado"
    ];

    public function usuario(){
        return $this->belongsTo(User::class,"user_id");
    }

    public function inventarios(){
        return $this->hasMany(Inventario::class);
    }

    public function presupuestos(){
        return $this->hasMany(Presupuesto::class);
    }

 
    
}
