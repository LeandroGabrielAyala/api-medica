<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $fillable = [

        'user_id',
        'tipo',
        'monto',
        'estado',
        'metodo_pago',
        'external_reference',
        'fecha_pago',
        'fecha',
        'hora',

    ];

    protected $casts = [

        'fecha_pago' => 'datetime',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}