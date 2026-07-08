<?php

namespace App\Http\Controllers;

use MercadoPago\Client\Payment\PaymentClient;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

            if ($tipo === 'turno') {
                $turnoValidation = $this->validarDatosTurno($request);

                if ($turnoValidation) {
                    return $turnoValidation;
                }

                if (
                    $this->horarioTurnoOcupado(
                        $request->input('metadata.fecha'),
                        $request->input('metadata.hora')
                    )
                ) {
                    return response()->json([
                        'error' => 'El horario seleccionado ya no esta disponible.'
                    ], 409);
                }
            }

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

                "notification_url" => config('services.mercadopago.webhook_url'),

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
    "Tu consulta fue atendida por un médico.",
"consultas",
[
        'screen' => 'consultas'
    ]
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

            PushNotificationService::send(
                $turno->user_id,
                "Turno atendido",
                "Tu turno fue marcado como atendido.",
"turnos",
[
        'screen' => 'turnos'
    ]
            );
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
    "Un médico comenzó a atender tu consulta.",
"consultas",
[
        'screen' => 'consultas'
    ]
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
    "Tu consulta fue finalizada por el médico.",
"consultas",
[
        'screen' => 'consultas'
    ]
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

            return DB::transaction(function () use ($request) {

                $pago = Pago::where(
                    'external_reference',
                    $request->external_reference
                )->lockForUpdate()->first();

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

            });
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

            $fecha = $pago->metadata['fecha'] ?? null;
            $hora = $pago->metadata['hora'] ?? null;

            if ($this->horarioTurnoOcupado($fecha, $hora)) {
                Log::warning('TURNO NO CREADO: HORARIO OCUPADO', [
                    'pago_id' => $pago->id,
                    'external_reference' => $pago->external_reference,
                    'fecha' => $fecha,
                    'hora' => $hora,
                ]);

                break;
            }

            try {
                Turno::create([
                    'user_id' => $pago->user_id,
                'tipo' => 'Turno Médico',
                    'monto' => $pago->monto,
                    'estado' => 'pendiente',
                    'metodo_pago' => 'mercadopago',
                    'external_reference' => $pago->external_reference,
                    'fecha_pago' => now(),
                    'fecha' => $fecha,
                    'hora' => $hora,
                ]);
            } catch (QueryException $e) {
                if (!$this->esViolacionUnique($e)) {
                    throw $e;
                }

                Log::warning('TURNO NO CREADO: RESTRICCION UNIQUE', [
                    'pago_id' => $pago->id,
                    'external_reference' => $pago->external_reference,
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'error' => $e->getMessage(),
                ]);

                break;
            }

            if ($medico) {

                PushNotificationService::send(
                    $medico->id,
                    'Nuevo turno',
                    'Hay una nueva solicitud de turno.',
"turnos",
[
        'screen' => 'turnos'
    ]
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
                    'Un paciente solicitó una consulta.',
'consultas',
[
        'screen' => 'consultas'
    ]
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
                    'Un paciente solicitó un certificado.',
'certificados',
[
        'screen' => 'certificados'
    ]
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
                    'Un paciente cargó un nuevo estudio para revisar.',
'estudios',
[
        'screen' => 'estudios'
    ]
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
                    'Un paciente solicitó una receta médica.',
'recetas',
[
        'screen' => 'recetas'
    ]
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
    ->orderByRaw("
        CASE
            WHEN estado = 'pendiente' THEN 1
            WHEN estado = 'en_atencion' THEN 2
            WHEN estado = 'atendido' THEN 3
            ELSE 4
        END
    ")
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
    ->orderByRaw("
        CASE
            WHEN estado = 'pendiente' THEN 1
            WHEN estado = 'en_atencion' THEN 2
            WHEN estado = 'atendido' THEN 3
            ELSE 4
        END
    ")
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

$paymentId = $request->input('data.id')
    ?? $request->input('id')
    ?? null;

if (!$paymentId) {

    Log::warning(
        'WEBHOOK SIN PAYMENT ID',
        $request->all()
    );

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

        return DB::transaction(function () use ($payment) {

        $pago = Pago::where(
            'external_reference',
            $payment->external_reference
        )->lockForUpdate()->first();

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

        });
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


private function validarDatosTurno(Request $request)
{
    $validator = validator($request->all(), [
        'metadata.fecha' => 'required|date_format:Y-m-d',
        'metadata.hora' => [
            'required',
            'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'
        ],
    ], [
        'metadata.fecha.required' => 'La fecha del turno es obligatoria.',
        'metadata.fecha.date_format' => 'La fecha del turno debe tener formato YYYY-MM-DD.',
        'metadata.hora.required' => 'La hora del turno es obligatoria.',
        'metadata.hora.regex' => 'La hora del turno debe tener formato HH:MM.',
    ]);

    if (!$validator->fails()) {
        return null;
    }

    return response()->json([
        'error' => 'Datos de turno invalidos.',
        'errors' => $validator->errors(),
    ], 422);
}

private function horarioTurnoOcupado($fecha, $hora): bool
{
    if (!$fecha || !$hora) {
        return false;
    }

    return Turno::where('fecha', $fecha)
        ->where('hora', $hora)
        ->where('estado', '!=', 'cancelado')
        ->exists();
}

private function esViolacionUnique(QueryException $e): bool
{
    return in_array($e->getCode(), ['23000', '23505'], true);
}




}
