<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoInmueble extends Model
{
    use HasFactory;
    protected $fillable = [
        'nombre_tipo_inmueble',
        'user_id',
        'estado',
    ];

    public function usuario(){
        return $this->belongsTo(User::class,"user_id");
    }
    public function inmuebles(){
        return $this->hasMany(Inmueble::class);
    }
    
}
