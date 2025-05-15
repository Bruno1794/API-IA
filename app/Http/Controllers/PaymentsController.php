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
        $hoje = Carbon::now()->format('Y-m-d');  // Aqui estamos pegando só a data


        // Define o intervalo de datas com base no filtro
        switch (strtolower($filtro)) {

            case 'Ontem':
                $inicio = Carbon::yesterday()->format('Y-m-d');
                $fim = Carbon::now()->endOfWeek()->format('Y-m-d');
                break;

            case 'Semanal':
                $inicio = Carbon::now()->startOfWeek()->format('Y-m-d');
                $fim = Carbon::now()->endOfMonth()->format('Y-m-d');
                break;

            case 'Mensal':
                $inicio = Carbon::now()->startOfWeek()->format('Y-m-d');
                $fim = Carbon::parse($hoje)->endOfMonth()->format('Y-m-d');
                break;

            case 'Anual':
                $inicio = Carbon::now()->startOfYear()->format('Y-m-d');
                $fim = Carbon::now()->endOfYear()->format('Y-m-d');
                break;

            case 'hoje':
            default:
                // Apenas o dia de hoje, no formato 'Y-m-d'
                $inicio = $hoje;
                $fim = $hoje;
                break;
        }

        $qtd = Payment::where('user_id', Auth::id())
            ->whereDate('created_at', '=', $hoje) // Usando o formato 'Y-m-d'
            ->count();

        $Recebidos = Payment::where('user_id', Auth::id())
            ->where('status', 'Pago')
            ->whereDate('created_at', '=', $hoje) // Aplicando o filtro de data antes do sum
            ->sum('valor_debito');  // Não precisa do ->first() aqui

        $pendente = Payment::where('user_id', Auth::id())
            ->where('status', 'Pendente')
            ->whereDate('created_at', '=', $hoje) // Aplicando o filtro de data antes do sum
            ->sum('valor_debito');  // Também não precisa do ->first() aqui

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
