<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentsController extends Controller
{
    //

    public function filtroPagamentos() :JsonResponse
    {
        $filtro = request()->input('filtro', 'hoje');

        // Data atual
        $hoje = Carbon::now()->format('Y-m-d');

        // Define o intervalo de datas com base no filtro
        switch (strtolower($filtro)) {
            case 'ontem':
                $inicio = Carbon::yesterday()->format('Y-m-d');
                $fim = Carbon::yesterday()->format('Y-m-d');
                break;

            case 'semanal':
                $inicio = Carbon::now()->startOfWeek()->format('Y-m-d');
                $fim = Carbon::now()->endOfWeek()->format('Y-m-d');
                break;

            case 'mensal':
                $inicio = Carbon::now()->startOfMonth()->format('Y-m-d');
                $fim = Carbon::now()->endOfMonth()->format('Y-m-d');
                break;

            case 'anual':
                $inicio = Carbon::now()->startOfYear()->format('Y-m-d');
                $fim = Carbon::now()->endOfYear()->format('Y-m-d');
                break;

            case 'hoje':
            default:
                $inicio = $hoje;
                $fim = $hoje;
                break;
        }

        $qtd = Payment::where('user_id', Auth::id())
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();

        $Recebidos = Payment::where('user_id', Auth::id())
            ->where('status', 'Pago')
            ->whereBetween('created_at', [$inicio, $fim])
            ->sum('valor_debito');

        $pendente = Payment::where('user_id', Auth::id())
            ->where('status', 'Pendente')
            ->whereBetween('created_at', [$inicio, $fim])
            ->sum('valor_debito');

        return response()->json([
            'success' => true,
            'filtro' => ucfirst($filtro),
            'pagamentos' => [
                'qtd' => $qtd,
                'recebidos' => $Recebidos,
                'pendente' => $pendente,
            ],
        ]);
    }

    public function update(Payment $payment, Request $request): JsonResponse
    {
        $userLogado = Auth::user();
        // Verifica se o cliente pertence ao usuário logado
        if ($payment->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }

        $payment->update([
            'data_pagamento' => Carbon::now()->toDateString(),
            'status' => 'PAGO',
        ]);

        return response()->json([
                'success' => true,
                'data' => $payment,
            ]
        );
    }
    public function destroy(Payment $payment): JsonResponse
    {
        $userLogado = Auth::user();
        if ($payment->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }
        $payment->delete();
        return response()->json([
            'success' => true,
            'data' => $payment,
        ],200);

    }



}
