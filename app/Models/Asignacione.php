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
}
