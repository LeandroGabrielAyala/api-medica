<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receta extends Model
{
    protected $fillable = [
        'user_id',
        'medico_id',
        'motivo',
        'medicamento',
        'urgente',
        'indicaciones',
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