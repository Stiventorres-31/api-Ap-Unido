<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proyecto extends Model
{
    protected $fillable =[

        "codigo_proyecto",
        "departamento_proyecto",
        "ciudad_municipio_proyecto",
        "direccion_proyecto",
        "fecha_inicio_proyecto",
        "fecha_final_proyecto",
        "estado",
        "user_id"
    ];

    public function usuario(){
        return $this->belongsTo(User::class,"user_id");
    }
    public function inmuebles(){
        return $this->hasMany(Inmueble::class);
    }
    public function presupuestos(){
        return $this->hasMany(Presupuesto::class);
    }
    public function asignaciones(){
        return $this->hasMany(Asignacione::class);
    }
    
}
