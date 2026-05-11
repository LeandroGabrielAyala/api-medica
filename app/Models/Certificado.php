<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificado extends Model
{
    protected $fillable = [
        'user_id',
        'medico_id',
        'tipo',
        'motivo',
        'observaciones',
        'archivo',
        'estado',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function medico()
    {
        return $this->belongsTo(User::class, 'medico_id');
    }
}