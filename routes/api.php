<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MedicamentoController;
use App\Http\Controllers\EstudioController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\CertificadoController;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/crear-pago', [PagoController::class, 'crearPago']);
Route::get('/pagos/{external_reference}', [
    PagoController::class,
    'estadoPago'
]);

Route::post('/mp/webhook', [PagoController::class, 'webhook']);
Route::post('/simular-pago', [PagoController::class,'simularPago']);

Route::get('/consultas', [PagoController::class, 'listarConsultas']);
Route::post(
    '/consultas/{id}/tomar',
    [PagoController::class, 'tomarConsulta']
);

Route::post(
    '/consultas/{id}/finalizar',
    [PagoController::class, 'finalizarConsulta']
);

Route::post(
    '/consultas/{id}/cancelar',
    [PagoController::class, 'cancelarConsulta']
);

Route::post(
    '/consultas/{id}/atender',
    [PagoController::class, 'marcarAtendido']
);

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

// CERTIFICADOS
Route::get('/mis-certificados/{user_id}', [CertificadoController::class, 'misCertificados']);
Route::get('/certificados', [CertificadoController::class, 'index']);
Route::post('/certificados', [CertificadoController::class, 'store']);
Route::get('/certificados/{id}', [CertificadoController::class, 'show']);
Route::post('/certificados/{id}/archivo', [CertificadoController::class, 'subirArchivo']);

// TURNOS
Route::get( '/turnos', [PagoController::class, 'listarTurnos']);
Route::post(
    '/turnos/{id}/atender',
    [PagoController::class, 'marcarTurnoAtendido']
);

// TEST PUSH NOTIFICATIONS
Route::get(
    '/test-push',
    [PagoController::class, 'testPush']
);


Route::post(
    '/estudios/upload-imagen',
    [EstudioController::class, 'uploadImagen']
);