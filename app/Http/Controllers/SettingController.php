<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    //

    public function index(): JsonResponse
    {
        $settings = Settings::where('user_id', auth()->id())->first();

        return response()->json([
            'success' => true,
            'data' => $settings
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {

        $userLogado = auth()->user();
        $setting = Settings::updateOrCreate(
            ['user_id' => $userLogado->id], // condição de busca
            [
                'time_cobranca' => $request->time_cobranca,
                'cadastro' => $request->cadastro ?? false,
                'notificar' => $request->notificar ?? false,
                'envio' => $request->envio ?? false,
                'msg_padrao' => $request->padrao ? $request->msg_padrao : null,

            ] // dados a atualizar ou inserir
        );
        return response()->json([
            'success' => true,
            'data' => $setting

        ], 200);
    }
}
