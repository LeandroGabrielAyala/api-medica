<?php

namespace App\Http\Controllers;

use App\Models\Certificado;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

class CertificadoController extends Controller
{
    // 👤 MIS CERTIFICADOS
    public function misCertificados($user_id)
    {
        return response()->json(

            Certificado::where('user_id', $user_id)
                ->latest()
                ->get()

        );
    }

    // 👨‍⚕️ TODAS LAS SOLICITUDES
    public function index()
    {
        return response()->json(

            Certificado::with('user')
                ->latest()
                ->get()

        );
    }

    // ➕ CREAR SOLICITUD
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'user_id' => 'required',
    //         'tipo' => 'required',
    //         'motivo' => 'required',
    //     ]);

    //     $certificado = Certificado::create([
    //         'user_id' => $request->user_id,
    //         'tipo' => $request->tipo,
    //         'motivo' => $request->motivo,
    //         'estado' => 'Pendiente',
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'data' => $certificado,
    //     ]);
    // }

    // 👁 VER DETALLE
    public function show($id)
    {
        return response()->json(

            Certificado::with(['user', 'medico'])
                ->findOrFail($id)

        );
    }

    // 📄 SUBIR PDF
    public function subirArchivo(Request $request, $id)
    {
        $request->validate([
            'archivo' => 'required|file',
        ]);

        $certificado = Certificado::findOrFail($id);

        $path = $request->file('archivo')
            ->store('certificados', 'public');

        $certificado->archivo = $path;

        $certificado->observaciones =
            $request->observaciones;

        $certificado->medico_id =
            $request->medico_id;

        $certificado->estado = 'Listo';


$certificado->save();

/*
|--------------------------------------------------------------------------
| PUSH AL PACIENTE
|--------------------------------------------------------------------------
*/

PushNotificationService::send(

    $certificado->user_id,

    "Certificado disponible",

    "Tu certificado médico ya está listo para descargar."

);


return  response()->json([
            'success' => true,
            'data' => $certificado,
        ]);
    }
}
