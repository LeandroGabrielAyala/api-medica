<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use App\Models\Consulta;
use App\Models\Notification;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                'user_id' => 'required|exists:users,id',
            ]);

            $tipo = $request->tipo;
            $monto = (float) $request->monto;
            $user = User::find($request->user_id);
            $externalReference =
                Str::uuid()->toString();
            $pago = Pago::create([
                'user_id' =>
                $request->user_id,
                'modulo' =>
                $request->tipo,
                'monto' =>
                $monto,
                'estado' =>
                'pendiente',
                'external_reference' =>
                $externalReference,
                'metadata' =>
                $request->metadata ?? [],
            ]);

            $client =
                new PreferenceClient();

            $preference =
                $client->create([

                    "items" => [
                        [
                            "title" =>
                            ucfirst($tipo),

                            "quantity" => (int) 1,

                            "unit_price" =>
                            (float) $monto,

                            "currency_id" =>
                            "ARS"
                        ]
                    ],

                    "payer" => [
                        "email" =>
                        $user->email
                    ],

                    "external_reference" =>
                    $pago->external_reference,

                    // "notification_url" =>
                    // env('MP_WEBHOOK_URL'),

                    "back_urls" => [

                        "success" =>
                        env('MP_SUCCESS_URL'),

                        "failure" =>
                        env('MP_FAILURE_URL'),

                        "pending" =>
                        env('MP_PENDING_URL')

                    ],

                    "auto_return" =>
                    "approved"

                ]);

            $pago->update([

                'metadata' => array_merge(

                    $pago->metadata ?? [],

                    [
                        'mp_preference_id' =>
                        $preference->id
                    ]

                )

            ]);

            return response()->json([

                "init_point" =>
                $preference->init_point,

                "external_reference" =>
                $externalReference

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

                $consulta->estado =
                    "atendido";

                $consulta->save();
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
        switch ($pago->modulo) {

            case 'turno':

                Consulta::create([

                    'user_id' =>
                    $pago->user_id,

                    'tipo' =>
                    'turno',

                    'monto' =>
                    $pago->monto,

                    'estado' =>
                    'pagado',

                    'metodo_pago' =>
                    'mercadopago',

                    'external_reference' =>
                    $pago->external_reference,

                    'fecha_pago' =>
                    now(),

                    'fecha' =>
                    $pago->metadata['fecha'] ?? null,

                    'hora' =>
                    $pago->metadata['hora'] ?? null,

                ]);

                break;

            case 'consulta-rapida':

            case 'consulta-urgente':

            case 'teleconsulta':

            case 'receta':

                $datos = $pago->metadata;

                \App\Models\Receta::create([

                    'user_id' =>
                    $pago->user_id,

                    'motivo' =>
                    $datos['motivo'] ?? '',

                    'medicamento' =>
                    $datos['medicamento'] ?? null,

                    'urgente' =>
                    $datos['urgente'] ?? false,

                    'estado' =>
                    'Pendiente'

                ]);

                break;

            case 'certificado':

                $datos =
                    $pago->metadata;

                \App\Models\Certificado::create([

                    'user_id' =>
                    $pago->user_id,

                    'tipo' =>
                    $datos['tipo'] ?? '',

                    'motivo' =>
                    $datos['motivo'] ?? '',

                    'estado' =>
                    'Pendiente'

                ]);

                break;

            case 'estudio':

                Consulta::create([

                    'user_id' =>
                    $pago->user_id,

                    'tipo' =>
                    $pago->modulo,

                    'monto' =>
                    $pago->monto,

                    'estado' =>
                    'pagado',

                    'metodo_pago' =>
                    'mercadopago',

                    'external_reference' =>
                    $pago->external_reference,

                    'fecha_pago' =>
                    now(),

                ]);

                break;
        }
    }

    public function listarConsultas(Request $request)
    {
        $consultas = Consulta::with('user')

            ->where(
                'user_id',
                $request->user_id
            )

            ->where(
                'tipo',
                '!=',
                'turno'
            )

            ->where(
                'estado',
                '!=',
                'pendiente_pago'
            )

            ->latest()

            ->get();

        return response()->json(
            $consultas
        );
    }

    public function listarTurnos()
    {
        try {

            $turnos = Consulta::with('user')

                ->where(
                    'tipo',
                    'turno'
                )

                ->where(
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
                $turnos
            );
        } catch (\Exception $e) {

            Log::error($e);

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
}
