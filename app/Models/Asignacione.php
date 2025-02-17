<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asignacione extends Model
{
    protected $fillable=[
        'inmueble_id',
        'proyecto_id',
        'materiale_id',
        "consecutivo",
        'user_id',
        'cantidad_material',
        'costo_material',
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
