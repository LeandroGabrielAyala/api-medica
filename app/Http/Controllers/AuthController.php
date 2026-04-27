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

                'email' => 'required|email',
                'password' => 'required'

            ]);

            $user = User::where(
                'email',
                $request->email
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
}