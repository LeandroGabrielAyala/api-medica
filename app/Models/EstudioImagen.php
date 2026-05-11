<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstudioImagen extends Model
{
    protected $fillable = [
        'estudio_id',
        'imagen',
    ];

    public function estudio()
    {
        return $this->belongsTo(Estudio::class);
    }
}