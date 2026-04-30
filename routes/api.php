<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PagoController;

use App\Http\Controllers\AuthController;

Route::post(
    '/login',
    [AuthController::class, 'login']
);

Route::post(
    '/crear-pago',
    [PagoController::class, 'crearPago']
);

Route::get('/mp/redirect', function () {

    $status = request('status');

    return redirect(
        "app-medica://confirmacion?status={$status}"
    );
});

Route::post(
    '/mp/webhook',
    [PagoController::class, 'webhook']
);

Route::get(
    '/consultas',
    [PagoController::class, 'listarConsultas']
);

Route::post(
    '/consultas/{id}/atender',
    [PagoController::class, 'marcarAtendido']
);

Route::get(
    '/notificaciones',
    [PagoController::class, 'listarNotificaciones']
);

Route::get(
    '/notificaciones/count',
    [PagoController::class, 'contarNotificaciones']
);

Route::post(
    '/notificaciones/read',
    [PagoController::class, 'marcarNotificacionesLeidas']
);

Route::post(
    '/guardar-token',
    [PagoController::class, 'guardarToken']
);
