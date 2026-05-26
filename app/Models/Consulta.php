<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consulta extends Model
{
    protected $fillable = [
        'user_id',
        'tipo',
        'descripcion',
        'monto',
        'metodo_pago',
        'estado',
        'external_reference',
        'fecha_pago',
        'fecha',
        'hora'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function medico()
    {
        return $this->belongsTo(
            User::class,
            'medico_id'
        );
    }
}
