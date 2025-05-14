<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    //

    public function Login(Request $request): JsonResponse
    {
        if (!Auth::attempt($request->only('username', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciais inválidas.',
            ], 401);
        }

        $user = Auth::user();

        if (now()->greaterThan($user->validate)) {
            return response()->json([
                'success' => false,
                'message' => 'Conta expirada.',
            ], 403);
        }

        $token = $user->createToken('CODESITEMA')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function Logout(): JsonResponse
    {
        $user = Auth::user();

        // Revoga apenas o token atual do usuário
        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

}
