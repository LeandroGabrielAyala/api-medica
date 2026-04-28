<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use App\Models\Consulta;
use Illuminate\Support\Facades\Log;

class PagoController extends Controller
{
    public function crearPago(Request $request)
    {
        try {

            MercadoPagoConfig::setAccessToken(
                env('MP_ACCESS_TOKEN')
            );

            $request->validate([

                'tipo' => 'required|string',
                'monto' => 'required|numeric'

            ]);

            $tipo = $request->tipo;
            $monto = (float) $request->monto;

            // Generar referencia única

            $externalReference = uniqid();

            // Crear consulta

            $consulta = Consulta::create([

                'tipo' => $tipo,
                'monto' => $monto,
                'estado' => 'pendiente_pago',
                'metodo_pago' => 'mercadopago',
                'external_reference' => $externalReference,

            ]);

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

                "external_reference" => $externalReference,

                "notification_url" => env('MP_WEBHOOK_URL'),

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

    public function marcarAtendido($id)
    {
        try {

            $consulta = Consulta::find($id);

            if ($consulta) {

                $consulta->estado =
                    "atendido";

                $consulta->save();
            }

            return response()->json([
                "status" => "ok"
            ]);
        } catch (\Exception $e) {

            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function webhook(Request $request)
    {
        try {

            $data = $request->all();

            // Log para debug

            Log::info("Webhook MP:", $data);

            if (isset($data['data']['id'])) {

                $paymentId = $data['data']['id'];

                MercadoPagoConfig::setAccessToken(
                    env('MP_ACCESS_TOKEN')
                );

                $client = new \MercadoPago\Client\Payment\PaymentClient();

                $payment = $client->get($paymentId);

                $externalReference =
                    $payment->external_reference;

                if ($payment->status === "approved") {

                    $consulta = Consulta::where(
                        'external_reference',
                        $externalReference
                    )->first();

                    if ($consulta) {

                        $consulta->estado = "pagado";

                        $consulta->fecha_pago = now();

                        $consulta->save();
                    }
                }
            }

            return response()->json([
                "status" => "ok"
            ]);
        } catch (\Exception $e) {

            Log::error($e->getMessage());

            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function listarConsultas()
    {
        try {

            $consultas = Consulta::where(
                'estado',
                '!=',
                'pendiente_pago'
            )
                ->orderBy(
                    'created_at',
                    'desc'
                )
                ->get();

            return response()->json(
                $consultas
            );
        } catch (\Exception $e) {

            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }
}
