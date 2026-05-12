<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use App\Models\Consulta;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\Pago;

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
                'monto' => 'required|numeric',
                'user_id' => 'required|exists:users,id', // 🔥 VALIDACIÓN IMPORTANTE
            ]);

            $tipo = $request->tipo;
            $monto = (float) $request->monto;
            $user = User::find($request->user_id);

            $externalReference = Str::uuid()->toString();

            $pago = Pago::create([
                'user_id' => $request->user_id,
                'modulo' => $request->tipo,
                'monto' => $monto,
                'estado' => 'pendiente',
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
                    "email" => $user->email
                ],

                "external_reference" => $pago->external_reference,

                "notification_url" => env('MP_WEBHOOK_URL'),

                "back_urls" => [
                    "success" => env('MP_SUCCESS_URL'),
                    "failure" => env('MP_FAILURE_URL'),
                    "pending" => env('MP_PENDING_URL')
                ],

                "auto_return" => "approved"

            ]);

            $pago->update([
                'metadata' => [
                    'mp_preference_id' => $preference->id
                ]
            ]);

            return response()->json([

                "init_point" => $preference->init_point,

                "external_reference" => $externalReference

            ]);
        } catch (\Exception $e) {

            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function estadoPago($externalReference)
    {
        $pago = Pago::where(
            'external_reference',
            $externalReference
        )->first();

        if (!$pago) {

            return response()->json([
                'error' => 'Pago no encontrado'
            ], 404);
        }

        return response()->json($pago);
    }

    public function marcarAtendido($id)
    {
        try {

            $consulta = Consulta::find($id);

            if ($consulta) {
                $consulta->estado = "atendido";
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

            Log::info("Webhook MP:", $data);

            if (isset($data['data']['id'])) {

                $paymentId = $data['data']['id'];

                MercadoPagoConfig::setAccessToken(
                    env('MP_ACCESS_TOKEN')
                );

                $client = new \MercadoPago\Client\Payment\PaymentClient();

                // $payment = $client->get($paymentId);
                $payment = new \stdClass();
                $payment->status = "approved";
                $payment->external_reference =
                    $data['external_reference'] ?? null;

                $externalReference = $payment->external_reference;

                if ($payment->status === "approved") {

                    $pago = Pago::where(
                        'external_reference',
                        $externalReference
                    )->first();

                    if ($pago) {

                        // ✅ EVITAR DUPLICADOS
                        if ($pago->estado === "pagado") {

                            return response()->json([
                                "status" => "ya procesado"
                            ]);
                        }

                        $pago->estado = "pagado";
                        $pago->fecha_pago = now();
                        $pago->mp_payment_id = $paymentId;
                        $pago->save();

                        Log::info("PAGO APROBADO", [
                            'pago_id' => $pago->id,
                            'modulo' => $pago->modulo,
                            'monto' => $pago->monto
                        ]);

                        Consulta::create([
                            'user_id' => $pago->user_id,
                            'tipo' => $pago->modulo,
                            'monto' => $pago->monto,
                            'estado' => 'pagado',
                            'metodo_pago' => 'mercadopago',
                            'external_reference' => $pago->external_reference,
                            'fecha_pago' => now(),
                        ]);

                        // 🔔 Notificación
                        Notification::create([
                            'title' => 'Nuevo pago aprobado',
                            'message' =>
                            'Se recibió un pago de ' .
                                $pago->modulo .
                                ' por $' .
                                number_format(
                                    $pago->monto,
                                    2,
                                    ',',
                                    '.'
                                ),

                            'read' => false
                        ]);

                        // 📲 Push (opcional)
                        $user = User::where('role', 'medico')->first();

                        if ($user && $user->push_token) {
                            Http::post(
                                'https://exp.host/--/api/v2/push/send',
                                [
                                    'to' => $user->push_token,
                                    'title' => 'Nueva consulta',
                                    'body' => 'Nuevo pago aprobado: ' . $pago->modulo,
                                ]
                            );
                        }
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

    public function simularPago(Request $request)
    {
        try {

            $pago = Pago::where(
                'external_reference',
                $request->external_reference
            )->first();

            if (!$pago) {

                return response()->json([
                    "error" => "Pago no encontrado"
                ], 404);
            }

            // ✅ EVITAR DUPLICADOS
            if ($pago->estado === "pagado") {

                return response()->json([
                    "status" => "ya procesado"
                ]);
            }

            // ✅ APROBAR PAGO
            $pago->estado = "pagado";

            $pago->fecha_pago = now();

            $pago->mp_payment_id = "SIMULADO";

            $pago->save();

            // ✅ LOG
            Log::info("PAGO SIMULADO", [

                'pago_id' => $pago->id,

                'modulo' => $pago->modulo,

                'monto' => $pago->monto

            ]);

            // ✅ CREAR CONSULTA
            Consulta::create([

                'user_id' => $pago->user_id,

                'tipo' => $pago->modulo,

                'monto' => $pago->monto,

                'estado' => 'pagado',

                'metodo_pago' => 'mercadopago',

                'external_reference' => $pago->external_reference,

                'fecha_pago' => now(),

            ]);

            // ✅ NOTIFICACIÓN
            Notification::create([

                'title' => 'Nuevo pago aprobado',

                'message' =>
                'Se recibió un pago de ' .
                    $pago->modulo .
                    ' por $' .
                    number_format(
                        $pago->monto,
                        2,
                        ',',
                        '.'
                    ),

                'read' => false

            ]);

            return response()->json([
                "success" => true
            ]);
        } catch (\Exception $e) {

            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function listarConsultas()
    {
        try {

            // 🔥 IMPORTANTE: traer usuario
            $consultas = Consulta::with('user')
                ->where('estado', '!=', 'pendiente_pago')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($consultas);
        } catch (\Exception $e) {

            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function listarNotificaciones()
    {
        return response()->json(
            Notification::orderBy('created_at', 'desc')->get()
        );
    }

    public function contarNotificaciones()
    {
        return response()->json([
            "total" => Notification::where('read', false)->count()
        ]);
    }

    public function marcarNotificacionesLeidas()
    {
        Notification::where('read', false)
            ->update(['read' => true]);

        return response()->json([
            "success" => true
        ]);
    }

    public function guardarToken(Request $request)
    {
        Log::info("TOKEN REQUEST:", $request->all());

        if (!$request->token || !$request->user_id) {
            return response()->json(["success" => false]);
        }

        $user = User::find($request->user_id);

        if ($user) {
            $user->push_token = $request->token;
            $user->save();
        }

        return response()->json([
            "success" => true
        ]);
    }
}
