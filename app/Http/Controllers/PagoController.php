<?php

namespace App\Http\Controllers;

use MercadoPago\Client\Payment\PaymentClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use App\Models\Consulta;
use App\Models\Notification;
use App\Models\Pago;
use App\Models\User;
use App\Models\Turno;
use App\Models\EstudioImagen;
use App\Models\Estudio;
use App\Models\Receta;
use App\Models\Certificado;
use App\Services\PushNotificationService;

class PagoController extends Controller
{
    public function crearPago(Request $request)
    {
        try {
            MercadoPagoConfig::setAccessToken(
                config('services.mercadopago.access_token')
            );

            $request->validate([
                'tipo' => 'required|string',
                'monto' => 'required|numeric',
                'user_id' => 'required|exists:users,id',
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
                'metadata' => $request->metadata ?? [],
            ]);

            $client = new PreferenceClient();

Log::info('MP CONFIG', [
    'token' => config('services.mercadopago.access_token'),
    'success' => config('services.mercadopago.success_url'),
    'failure' => config('services.mercadopago.failure_url'),
    'pending' => config('services.mercadopago.pending_url'),
]);

            $preference = $client->create([
                "items" => [
                    [
                        "title" => ucfirst($tipo),
                        "quantity" => (int) 1,
                        "unit_price" => (float) $monto,
                        "currency_id" => "ARS"
                    ]
                ],

                "payer" => [
                    "email" => $user->email
                ],

                "external_reference" => $pago->external_reference,

                "notification_url" =>
                env('MP_WEBHOOK_URL'),

                "back_urls" => [
                    "success" => config('services.mercadopago.success_url'),
                    "failure" => config('services.mercadopago.failure_url'),
                    "pending" => config('services.mercadopago.pending_url'),
                ],

                "auto_return" => "approved"
            ]);

            $pago->update([
                'metadata' => array_merge(
                    $pago->metadata ?? [],
                    [
                        'mp_preference_id' => $preference->id
                    ]
                )
            ]);

            return response()->json([
                "init_point" => $preference->init_point,
                "external_reference" => $externalReference
            ]);
        } catch (\Exception $e) {

            Log::error("ERROR MP", [
                'mensaje' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([

                "error" => $e->getMessage(),

                "trace" => $e->getTraceAsString()

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

            $consulta =
                Consulta::find($id);

            if ($consulta) {

                $consulta->estado = "atendido";
                $consulta->save();
PushNotificationService::send(
    $consulta->user_id,
    "Consulta atendida",
    "Tu consulta fue atendida por un médico."
);
            }

            return response()->json([
                "status" => "ok"
            ]);
        } catch (\Exception $e) {

            Log::error($e);

            return response()->json([
                "error" => $e->getMessage(),
                "line" => $e->getLine(),
                "file" => $e->getFile(),
            ], 500);
        }
    }

    public function marcarTurnoAtendido($id)
    {
        try {

            $turno = Turno::find($id);

            if ($turno) {

                $turno->estado = "atendido";

                $turno->save();
            }

            return response()->json([
                "status" => "ok"
            ]);
        } catch (\Exception $e) {

            return response()->json([

                "error" => $e->getMessage(),

            ], 500);
        }
    }

    public function tomarConsulta($id)
    {
        try {

            $consulta =
                Consulta::findOrFail($id);

            $consulta->estado =
                "en_atencion";

            $consulta->fecha_inicio =
                now();

            $consulta->save();

PushNotificationService::send(
    $consulta->user_id,
    "Consulta en atención",
    "Un médico comenzó a atender tu consulta."
);

            return response()->json([
                "success" => true
            ]);
        } catch (\Exception $e) {

            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function finalizarConsulta($id)
    {
        try {

            $consulta =
                Consulta::findOrFail($id);

            $consulta->estado =
                "finalizado";

            $consulta->fecha_finalizacion =
                now();

            $consulta->save();

PushNotificationService::send(
    $consulta->user_id,
    "Consulta finalizada",
    "Tu consulta fue finalizada por el médico."
);

            return response()->json([
                "success" => true
            ]);
        } catch (\Exception $e) {

            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function cancelarConsulta($id)
    {
        try {

            $consulta =
                Consulta::findOrFail($id);

            $consulta->estado =
                "cancelado";

            $consulta->save();

            return response()->json([
                "success" => true
            ]);
        } catch (\Exception $e) {

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
                    "error" =>
                    "Pago no encontrado"
                ], 404);
            }

            // ✅ evitar duplicados
            if (
                $pago->estado === "pagado"
            ) {

                return response()->json([
                    "status" =>
                    "ya procesado"
                ]);
            }

            // ✅ aprobar
            $pago->estado =
                "pagado";

            $pago->fecha_pago =
                now();

            $pago->mp_payment_id =
                "SIMULADO";

            $pago->save();

            Log::info(
                "PAGO SIMULADO",
                [

                    'pago_id' =>
                    $pago->id,

                    'modulo' =>
                    $pago->modulo,

                    'monto' =>
                    $pago->monto

                ]
            );

            // ✅ PROCESAR MÓDULO
            $this->procesarModulo($pago);

            // 🔔 NOTIFICACIÓN
            Notification::create([

                'title' =>
                'Nuevo pago aprobado',

                'message' =>
                'Se recibió un pago de ' .
                    strtoupper($pago->modulo) .
                    ' por $' .
                    number_format(
                        $pago->monto,
                        2,
                        ',',
                        '.'
                    ),

                'read' => false,

            ]);

            return response()->json([
                "success" => true
            ]);
        } catch (\Exception $e) {

            Log::error($e);

            return response()->json([

                "error" => $e->getMessage(),

                "line" => $e->getLine(),

                "file" => $e->getFile(),

            ], 500);
        }
    }



private function procesarModulo($pago)
{
    $medico = User::where(
        'role',
        'medico'
    )->first();

    switch ($pago->modulo) {

        case 'turno':

            Turno::create([
                'user_id' => $pago->user_id,
                'tipo' => 'Turno Médico',
                'monto' => $pago->monto,
                'estado' => 'pendiente',
                'metodo_pago' => 'mercadopago',
                'external_reference' => $pago->external_reference,
                'fecha_pago' => now(),
                'fecha' => $pago->metadata['fecha'] ?? null,
                'hora' => $pago->metadata['hora'] ?? null,
            ]);

            if ($medico) {

                PushNotificationService::send(
                    $medico->id,
                    'Nuevo turno',
                    'Hay una nueva solicitud de turno.'
                );
            }

            break;

        case 'consulta-rapida':

        case 'consulta-urgente':

        case 'teleconsulta':

            Consulta::create([
                'user_id' => $pago->user_id,
                'tipo' => $pago->modulo,
                'monto' => $pago->monto,
                'estado' => 'pendiente',
                'metodo_pago' => 'mercadopago',
                'external_reference' => $pago->external_reference,
                'fecha_pago' => now(),
            ]);

            if ($medico) {

                PushNotificationService::send(
                    $medico->id,
                    'Nueva consulta',
                    'Un paciente solicitó una consulta.'
                );
            }

            break;

        case 'certificado':

            $datos = $pago->metadata;

            Certificado::create([
                'user_id' => $pago->user_id,
                'tipo' => $datos['tipo'] ?? '',
                'motivo' => $datos['motivo'] ?? '',
                'estado' => 'Pendiente'
            ]);

            if ($medico) {

                PushNotificationService::send(
                    $medico->id,
                    'Nuevo certificado',
                    'Un paciente solicitó un certificado.'
                );
            }

            break;

        case 'estudio':

            $estudio = Estudio::create([
                'user_id' => $pago->user_id,
                'descripcion' => $pago->metadata['descripcion'] ?? '',
                'estado' => 'Pendiente',
            ]);

            foreach (
                ($pago->metadata['imagenes'] ?? [])
                as $imagen
            ) {

                EstudioImagen::create([

                    'estudio_id' =>
                    $estudio->id,

                    'imagen' =>
                    $imagen,

                ]);
            }

            if ($medico) {

                PushNotificationService::send(
                    $medico->id,
                    'Nuevo estudio',
                    'Un paciente cargó un nuevo estudio para revisar.'
                );
            }

            break;

        case 'receta':

            $datos = $pago->metadata;

            Receta::create([
                'user_id' => $pago->user_id,
                'motivo' => $datos['motivo'] ?? '',
                'medicamento' => $datos['medicamento'] ?? '',
                'urgente' => $datos['urgente'] ?? false,
                'estado' => 'Pendiente',
            ]);

            if ($medico) {

                PushNotificationService::send(
                    $medico->id,
                    'Nueva receta',
                    'Un paciente solicitó una receta médica.'
                );
            }

            break;
    }
}



    public function listarConsultas(Request $request)
    {
        try {

            $query = Consulta::with('user')

                ->where(
                    'tipo',
                    '!=',
                    'turno'
                )

                ->where(
                    'estado',
                    '!=',
                    'pendiente_pago'
                );

            // 👨‍⚕️ PANEL MÉDICO
            if ($request->modo === "medico") {

                $consultas = $query
                    ->latest()
                    ->get();
            }

            // 👤 PACIENTE
            else {

                $consultas = $query

                    ->where(
                        'user_id',
                        $request->user_id
                    )

                    ->latest()

                    ->get();
            }

            return response()->json($consultas);
        } catch (\Exception $e) {

            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function listarTurnos(Request $request)
    {
        try {

            $query = Turno::with('user');

            // PANEL MÉDICO
            if ($request->modo === "medico") {

                $turnos = $query

                    ->orderBy('created_at', 'desc')

                    ->get();

                return response()->json($turnos);
            }

            // PANEL PACIENTE

            $turnos = $query

                ->where(
                    'user_id',
                    $request->user_id
                )

                ->orderBy('created_at', 'desc')

                ->get();

            return response()->json($turnos);
        } catch (\Exception $e) {

            return response()->json([

                "error" => $e->getMessage(),

                "line" => $e->getLine(),

                "file" => $e->getFile(),

            ], 500);
        }
    }

    public function listarNotificaciones()
    {
        return response()->json(

            Notification::orderBy(
                'created_at',
                'desc'
            )->get()

        );
    }

    public function contarNotificaciones()
    {
        return response()->json([

            "total" => Notification::where(
                'read',
                false
            )->count()

        ]);
    }

    public function marcarNotificacionesLeidas()
    {
        Notification::where(
            'read',
            false
        )->update([
            'read' => true
        ]);

        return response()->json([
            "success" => true
        ]);
    }

    public function guardarToken(Request $request)
    {
        Log::info(
            "TOKEN REQUEST:",
            $request->all()
        );

        if (
            !$request->token ||
            !$request->user_id
        ) {

            return response()->json([
                "success" => false
            ]);
        }

        $user = User::find(
            $request->user_id
        );

        if ($user) {

            $user->push_token =
                $request->token;

            $user->save();
        }

        return response()->json([
            "success" => true
        ]);
    }

    public function testPush()
    {
        $medico = User::where(
            'role',
            'medico'
        )->first();

        if (
            !$medico ||
            !$medico->push_token
        ) {

            return response()->json([
                'error' => 'No existe token'
            ]);
        }

        $response = Http::post(
            'https://exp.host/--/api/v2/push/send',
            [
                'to' => $medico->push_token,

                'title' => 'Prueba Push',

                'body' => 'Notificación enviada desde Laravel',

                'sound' => 'default',
            ]
        );

        return response()->json([
            'success' => true,
            'expo' => $response->json()
        ]);
    }




public function webhook(Request $request)
{
    try {

        Log::info('WEBHOOK MP', $request->all());

        MercadoPagoConfig::setAccessToken(
            config('services.mercadopago.access_token')
        );

        $paymentId = null;

        if (isset($request->data['id'])) {
            $paymentId = $request->data['id'];
        }

        if (!$paymentId) {
            return response()->json([
                'ok' => true
            ]);
        }

        $client = new PaymentClient();

        $payment = $client->get($paymentId);

        Log::info('PAYMENT MP', [
            'id' => $payment->id,
            'status' => $payment->status,
            'external_reference' => $payment->external_reference,
        ]);

        if ($payment->status !== 'approved') {

            return response()->json([
                'ok' => true
            ]);
        }

        $pago = Pago::where(
            'external_reference',
            $payment->external_reference
        )->first();

        if (!$pago) {

            Log::warning(
                'PAGO NO ENCONTRADO',
                [
                    'external_reference' =>
                    $payment->external_reference
                ]
            );

            return response()->json([
                'ok' => true
            ]);
        }

        if ($pago->estado === 'pagado') {

            return response()->json([
                'ok' => true
            ]);
        }

        $pago->estado = 'pagado';

        $pago->mp_payment_id =
            $payment->id;

        $pago->fecha_pago =
            now();

        $pago->save();

        Log::info(
            'PAGO APROBADO',
            [
                'pago_id' => $pago->id,
                'modulo' => $pago->modulo
            ]
        );

        $this->procesarModulo($pago);

        Notification::create([

            'title' =>
            'Nuevo pago aprobado',

            'message' =>
            'Se recibió un pago de ' .
            strtoupper($pago->modulo) .
            ' por $' .
            number_format(
                $pago->monto,
                2,
                ',',
                '.'
            ),

            'read' => false,
        ]);

        return response()->json([
            'ok' => true
        ]);
    } catch (\Exception $e) {

        Log::error(
            'ERROR WEBHOOK MP',
            [
                'mensaje' =>
                $e->getMessage(),

                'trace' =>
                $e->getTraceAsString()
            ]
        );

        return response()->json([
            'ok' => false
        ], 500);
    }
}




}
