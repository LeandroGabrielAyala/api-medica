<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estudio extends Model
{
    protected $fillable = [
        'user_id',
        'descripcion',
        'estado',
        'resultado',
    ];

    public function imagenes()
    {
        return $this->hasMany(EstudioImagen::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}