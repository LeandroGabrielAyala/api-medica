<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {

            $request->validate([

                'dni' => 'required',
                'password' => 'required'

            ]);

            $user = User::where(
                'dni',
                $request->dni
            )->first();

            if (
                !$user ||
                !Hash::check(
                    $request->password,
                    $user->password
                )
            ) {

                return response()->json([
                    "error" => "Credenciales incorrectas"
                ], 401);
            }

            return response()->json([

                "id" => $user->id,
                "name" => $user->name,
                "email" => $user->email,
                "role" => $user->role

            ]);
        } catch (\Exception $e) {

            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function validarRegistro(Request $request)
    {
        return response()->json([

            'dni_existe' => User::where(
                'dni',
                $request->dni
            )->exists(),

            'email_existe' => User::where(
                'email',
                $request->email
            )->exists(),

        ]);
    }

    public function register(Request $request)
    {
        $request->validate([

            'email' =>
            'required|email|unique:users,email',

            'password' =>
            'required|min:6',

            'dni' =>
            'required|unique:users,dni',

        ]);

        $user = User::create([

            'email' =>
            $request->email,

            'password' =>
            Hash::make(
                $request->password
            ),

            'dni' =>
            $request->dni,

            'name' =>
            'Paciente',

            'role' =>
            'paciente',

        ]);

        return response()->json([
            'success' => true,
            'user_id' => $user->id
        ]);
    }

    public function guardarDatosFiliatorios(Request $request)
    {
        $user = User::where(
            'email',
            $request->email
        )->first();

        if (!$user) {

            return response()->json([
                'error' => 'Usuario no encontrado'
            ], 404);
        }

        $user->update([
            'name' => $request->nombre,
            'apellido' => $request->apellido,
            'telefono' => $request->telefono,
            'direccion' => $request->direccion,
        ]);

        return response()->json([
            'success' => true
        ]);
    }
}
