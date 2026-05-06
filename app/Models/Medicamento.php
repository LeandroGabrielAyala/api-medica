<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicamento extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'stock',
    ];

    public function solicitudes()
    {
        return $this->hasMany(SolicitudMedicamento::class);
    }
}
