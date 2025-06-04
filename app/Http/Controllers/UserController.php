<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

    public function storeLed(Request $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'username' => explode(' ', $request->name)[0].substr($request->phone, -4),
            'validate' => Carbon::now()->addDays(2),
            'email' => $request->email,
            'password' => Hash::make('121314',['rounds' => 12]),
        ]);

        $dataFormata = Carbon::parse($user->validate)->setTimezone('America/Sao_Paulo');
        return response()->json([
            'Link' => 'https://si.codeacode.com.br/',
            'Usuário' => $user->username,
            'Senha' => "121314",
            'validate' => $dataFormata->format('d/m/Y'),
        ]);
    }
}
