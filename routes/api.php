<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MedicamentoController;
use App\Http\Controllers\EstudioController;
use App\Http\Controllers\RecetaController;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/crear-pago', [PagoController::class, 'crearPago']);

/**
 * 🔁 REDIRECT DESDE MERCADOPAGO
 */
Route::get('/mp/redirect', function () {

    $status = request('status');

    return redirect("https://danita-laccolithic-willodean.ngrok-free.dev/api/confirmacion?status={$status}");
});

/**
 * ✅ PÁGINA DE CONFIRMACIÓN (EVITA 404)
 */
Route::get('/confirmacion', function () {

    $status = request('status');

    return "
        <h1 style='font-family:sans-serif;'>Pago {$status}</h1>
        <p style='font-family:sans-serif;'>Ya podés volver a la app.</p>
    ";
});

Route::post('/mp/webhook', [PagoController::class, 'webhook']);
Route::get('/consultas', [PagoController::class, 'listarConsultas']);
Route::post('/consultas/{id}/atender', [PagoController::class, 'marcarAtendido']);
Route::get('/notificaciones', [PagoController::class, 'listarNotificaciones']);
Route::get('/notificaciones/count', [PagoController::class, 'contarNotificaciones']);
Route::post('/notificaciones/read', [PagoController::class, 'marcarNotificacionesLeidas']);
Route::post('/guardar-token', [PagoController::class, 'guardarToken']);


// 📦 medicamentos disponibles
Route::get('/medicamentos', [MedicamentoController::class, 'index']);

// 📝 solicitudes paciente
Route::get('/mis-medicamentos/{user_id}', [MedicamentoController::class, 'misSolicitudes']);

// ➕ solicitar medicamento
Route::post('/medicamentos/solicitar', [MedicamentoController::class, 'solicitar']);

// 👨‍⚕️ acciones médico
Route::post('/medicamentos/{id}/aprobar', [MedicamentoController::class, 'aprobar']);
Route::post('/medicamentos/{id}/rechazar', [MedicamentoController::class, 'rechazar']);
Route::post('/medicamentos/{id}/entregar', [MedicamentoController::class, 'entregar']);

// 📋 todas las solicitudes (panel médico)
Route::get('/medicamentos-solicitudes', [MedicamentoController::class, 'todasSolicitudes']);

// ➕ crear medicamento
Route::post('/medicamentos', [MedicamentoController::class, 'store']);

// actualizar stock
Route::put('/medicamentos/{id}', [MedicamentoController::class, 'update']);


// 🧪 ESTUDIOS
Route::get('/mis-estudios/{user_id}', [EstudioController::class, 'misEstudios']);
Route::post('/estudios', [EstudioController::class, 'store']);
Route::get('/estudios', [EstudioController::class, 'index']);
Route::post('/estudios/{id}/estado', [EstudioController::class, 'estado']);
Route::post('/estudios/{id}/resultado', [EstudioController::class, 'subirResultado']);
Route::get('/estudios/{id}', [EstudioController::class, 'show']);


// 🧾 RECETAS
Route::get('/mis-recetas/{user_id}', [RecetaController::class, 'misRecetas']);
Route::get('/recetas', [RecetaController::class, 'index']);
Route::post('/recetas', [RecetaController::class, 'store']);
Route::get('/recetas/{id}', [RecetaController::class, 'show']);
Route::post('/recetas/{id}/archivo', [RecetaController::class, 'subirArchivo']);