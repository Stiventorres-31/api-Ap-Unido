<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    protected $fillable=[
        "user_id",
        "materiale_id",
        "consecutivo",
        "costo",
        "cantidad",
        "nit_proveedor",
        "nombre_proveedor",
        "estado"
    ];

   

    public function usuario(){
        return $this->belongsTo(User::class,"user_id");
    }

    public function materiale(){
        return $this->belongsTo(Materiale::class);
    }
}
