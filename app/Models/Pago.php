<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $fillable = [

        'user_id',
        'modulo',
        'modulo_id',
        'monto',
        'estado',
        'external_reference',
        'mp_payment_id',
        'fecha_pago',
        'metadata',

    ];

    protected $casts = [
        'metadata' => 'array',
        'fecha_pago' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}