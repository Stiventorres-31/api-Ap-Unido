<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inmueble extends Model
{
    protected $fillable = [
        'estado',
        'proyecto_id',
        'user_id',
        'tipo_inmueble_id',
    ];

    public function usuario(){
        return $this->belongsTo(User::class,"user_id");
    }
    public function tipo_inmueble(){
        return $this->belongsTo(TipoInmueble::class);
    }
    public function proyecto(){
        return $this->belongsTo(Proyecto::class);
    }

    public function presupuestos(){
        return $this->hasMany(Presupuesto::class);
    }

    public function asignaciones(){
        return $this->hasMany(Asignacione::class);
    }
}
