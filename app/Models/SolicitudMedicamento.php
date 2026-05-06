<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudMedicamento extends Model
{
    protected $table = 'solicitud_medicamentos';

    protected $fillable = [
        'user_id',
        'medicamento_id',
        'estado',
    ];

    public function medicamento()
    {
        return $this->belongsTo(Medicamento::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}