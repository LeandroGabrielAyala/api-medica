<?php

namespace App\Http\Controllers;

use App\Models\Receta;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

class RecetaController extends Controller
{
    // 📋 MIS RECETAS
    public function misRecetas($user_id)
    {
        return response()->json(

            Receta::with('medico')
                ->where('user_id', $user_id)
                ->latest()
                ->get()

        );
    }

    // 👨‍⚕️ TODAS
    public function index()
    {
        return response()->json(

            Receta::with(['user', 'medico'])
                ->latest()
                ->get()

        );
    }

    // ➕ CREAR SOLICITUD
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'motivo' => 'required',
        ]);

        $receta = Receta::create([
            'user_id' => $request->user_id,
            'motivo' => $request->motivo,
            'medicamento' => $request->medicamento,
            'urgente' => $request->urgente == "true",
            'estado' => 'Pendiente',
        ]);

        return response()->json([
            "success" => true,
            "data" => $receta
        ]);
    }

    // 👁 DETALLE
    public function show($id)
    {
        return response()->json(
            Receta::with(['user', 'medico'])
                ->findOrFail($id)
        );
    }

    // 📄 SUBIR RECETA PDF
    public function subirArchivo(Request $request, $id)
    {
        $request->validate([
            'archivo' => 'required|file',
        ]);

        $receta = Receta::findOrFail($id);
        $path = $request->file('archivo')->store('recetas', 'public');
       

$receta->archivo = $path;
$receta->estado = "Lista";
$receta->medico_id = $request->medico_id;
$receta->indicaciones = $request->indicaciones;

$receta->save();

PushNotificationService::send(

    $receta->user_id,

    "Nueva receta disponible",

    "Tu receta médica ya fue emitida y está disponible para descargar."

);

return response()->json([
            "success" => true,
            "data" => $receta
        ]);
    }
}
