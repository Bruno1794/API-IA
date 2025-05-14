<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    //
    public function show(): JsonResponse
    {
        if (Auth::check()) {
            return response()->json([
                'success' => true,
                'user' => Auth::user()
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Usuário não autenticado'
        ], 401); // 401 é o código HTTP para "Não autorizado"
    }
}
