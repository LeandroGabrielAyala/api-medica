<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Medicamento;
use App\Models\SolicitudMedicamento;

class MedicamentoController extends Controller
{
    // 📦 LISTAR DISPONIBLES
    public function index()
    {
        return response()->json(
            Medicamento::where('stock', '>', 0)->get()
        );
    }

    // 📝 MIS SOLICITUDES
    public function misSolicitudes($user_id)
    {
        return response()->json(
            SolicitudMedicamento::with('medicamento')
                ->where('user_id', $user_id)
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    // ➕ SOLICITAR
    public function solicitar(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'medicamento_id' => 'required|exists:medicamentos,id',
        ]);

        $userId = $request->user_id;
        $medicamentoId = $request->medicamento_id;

        $medicamento = Medicamento::find($medicamentoId);

        // ❌ SIN STOCK
        if (!$medicamento || $medicamento->stock <= 0) {
            return response()->json([
                "error" => "Sin stock"
            ], 400);
        }

        // 🔥 CONTROL SEMANAL (CLAVE)
        $cantidadSemana = SolicitudMedicamento::where('user_id', $userId)
            ->where('medicamento_id', $medicamentoId)
            ->whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])
            ->count();

        if ($cantidadSemana >= 5) {
            return response()->json([
                "error" => "Máximo 5 por semana"
            ], 400);
        }

        // ✅ CREAR SOLICITUD
        $solicitud = SolicitudMedicamento::create([
            'user_id' => $userId,
            'medicamento_id' => $medicamentoId,
            'estado' => 'pendiente',
        ]);

        // 🔥 DESCONTAR STOCK
        $medicamento->decrement('stock');

        return response()->json([
            "success" => true,
            "data" => $solicitud
        ]);
    }

    // 👨‍⚕️ APROBAR
    public function aprobar($id)
    {
        $s = SolicitudMedicamento::find($id);

        if ($s) {
            $s->estado = "aprobado";
            $s->save();
        }

        return response()->json(["success" => true]);
    }

    // ❌ RECHAZAR
    public function rechazar($id)
    {
        $s = SolicitudMedicamento::find($id);

        if ($s) {
            $s->estado = "rechazado";
            $s->save();
        }

        return response()->json(["success" => true]);
    }

    // 📦 ENTREGAR
    public function entregar($id)
    {
        $s = SolicitudMedicamento::find($id);

        if ($s) {
            $s->estado = "entregado";
            $s->save();
        }

        return response()->json(["success" => true]);
    }

    public function todasSolicitudes()
    {
        return response()->json(
            \App\Models\SolicitudMedicamento::with(['medicamento', 'user'])
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string',
            'descripcion' => 'nullable|string',
            'stock' => 'required|integer|min:0'
        ]);

        $med = Medicamento::create($request->all());

        return response()->json([
            "success" => true,
            "data" => $med
        ]);
    }

    public function update(Request $request, $id)
    {
        $med = Medicamento::find($id);

        if (!$med) {
            return response()->json([
                "error" => "Medicamento no encontrado"
            ], 404);
        }

        $med->update([
            'stock' => $request->stock
        ]);

        return response()->json([
            "success" => true,
            "data" => $med
        ]);
    }
}
