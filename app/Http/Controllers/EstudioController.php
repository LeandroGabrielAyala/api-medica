<?php

namespace App\Http\Controllers;

use App\Models\Estudio;
use App\Models\EstudioImagen;
use Illuminate\Http\Request;

class EstudioController extends Controller
{
    // 📋 MIS ESTUDIOS
    public function misEstudios($user_id)
    {
        return response()->json(
            Estudio::with('imagenes')
                ->where('user_id', $user_id)
                ->latest()
                ->get()
        );
    }

    // ➕ CREAR
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'descripcion' => 'required',
        ]);

        $estudio = Estudio::create([
            'user_id' => $request->user_id,
            'descripcion' => $request->descripcion,
            'estado' => 'Pendiente',
        ]);

        // 🔥 GUARDAR IMÁGENES
        if ($request->hasFile('imagenes')) {

            foreach ($request->file('imagenes') as $imagen) {

                $path = $imagen->store('estudios', 'public');

                EstudioImagen::create([
                    'estudio_id' => $estudio->id,
                    'imagen' => $path,
                ]);
            }
        }

        return response()->json([
            "success" => true,
            "data" => $estudio
        ]);
    }

    // 👨‍⚕️ TODAS LAS SOLICITUDES
    public function index()
    {
        return response()->json(
            Estudio::with(['imagenes', 'user'])
                ->latest()
                ->get()
        );
    }

    // 🔄 CAMBIAR ESTADO
    public function estado(Request $request, $id)
    {
        $estudio = Estudio::find($id);

        if (!$estudio) {
            return response()->json([
                "error" => "No encontrado"
            ], 404);
        }

        $estudio->estado = $request->estado;
        $estudio->save();

        return response()->json([
            "success" => true
        ]);
    }

    public function subirResultado(Request $request, $id)
    {
        $request->validate([
            'resultado' => 'required|file',
        ]);

        $estudio = Estudio::find($id);

        if (!$estudio) {
            return response()->json([
                "error" => "Estudio no encontrado"
            ], 404);
        }

        $path = $request->file('resultado')
            ->store('resultados', 'public');

        $estudio->resultado = $path;
        $estudio->estado = "Listo";

        $estudio->save();

        return response()->json([
            "success" => true,
            "data" => $estudio
        ]);
    }

    public function show($id)
    {
        return response()->json(
            Estudio::with('imagenes')->findOrFail($id)
        );
    }
}
