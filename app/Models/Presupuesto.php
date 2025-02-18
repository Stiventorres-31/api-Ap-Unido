<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Presupuesto extends Model
{
    protected $fillable = [
        'inmueble_id',
        'materiale_id',
        'costo_material',
        'cantidad_material',
        'proyecto_id',
        'user_id',
        'subtotal',
        'estado'
    ];

    public function usuario(){
        return $this->belongsTo(User::class,"user_id");
    }

    public function materiale(){
        return $this->belongsTo(Materiale::class);
    }

    public function proyecto(){
        return $this->belongsTo(Proyecto::class);
    }
    public function inmueble(){
        return $this->belongsTo(Inmueble::class);
    }
}
