<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PagoController;

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