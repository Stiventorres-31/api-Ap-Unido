<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoInmueble extends Model
{
    protected $fillable = [
        'nombre_tipo_inmueble',
        'user_id',
        'estado',
    ];

    public function usuarios(){
        return $this->belongsTo(User::class);
    }
    public function inmuebles(){
        return $this->hasMany(Inmueble::class);
    }
    
}
