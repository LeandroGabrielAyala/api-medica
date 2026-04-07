<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;

class PagoController extends Controller
{
    public function crearPago(Request $request)
    {
        try {

            MercadoPagoConfig::setAccessToken(
                env('MP_ACCESS_TOKEN')
            );

            $tipo = $request->tipo;
            $monto = (float) $request->monto;

            $client = new PreferenceClient();

            $preference = $client->create([

                "items" => [
                    [
                        "title" => $tipo,
                        "quantity" => 1,
                        "unit_price" => $monto,
                        "currency_id" => "ARS"
                    ]
                ],

                "payer" => [
                    "email" => "test_user@testuser.com"
                ],

                "external_reference" => uniqid(),

                "back_urls" => [
                    "success" => "exp://192.168.100.4:8081/--/confirmacion",
                    "failure" => "exp://192.168.100.4:8081/--/confirmacion",
                    "pending" => "exp://192.168.100.4:8081/--/confirmacion"
                ],

                "auto_return" => "approved"

            ]);

            return response()->json([
                "init_point" => $preference->init_point
            ]);

        } catch (\Exception $e) {

            return response()->json([
                "error" => $e->getMessage()
            ], 500);

        }
    }
}