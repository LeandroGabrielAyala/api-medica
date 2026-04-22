<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consulta extends Model
{
    protected $fillable = [

        'tipo',
        'monto',
        'metodo_pago',
        'estado',
        'external_reference',
        'fecha_pago'

    ];
}