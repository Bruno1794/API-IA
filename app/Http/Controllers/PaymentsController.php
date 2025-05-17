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
    public function index(): JsonResponse
    {
        $user = Auth::user();

        // Captura os campos de pesquisa e status
        $pesquisa = strtolower(request()->input('pesquisa'));
        $status = ucfirst(strtolower(request()->input('status', 'pendente'))); // Status padrão: PENDENTE

        // Inicia a query com o usuário autenticado
        $query = Payment::with('client')
            ->where('user_id', $user->id)
            ->where('status', $status);  // Filtra pelo status

        // Aplica o filtro de pesquisa em nome ou telefone
        if (!empty($pesquisa)) {
            $query->where(function ($q) use ($pesquisa) {
                $q->orWhereHas('client', function ($query) use ($pesquisa) {
                    $query->where('name', 'LIKE', '%' . $pesquisa . '%')     // Nome do cliente
                    ->orWhere('phone', 'LIKE', '%' . $pesquisa . '%'); // Telefone do cliente
                });
            });
        }

        // Ordena por data de criação e pagina os resultados
        $pagamentos = $query->orderBy('data_criado', 'asc')->paginate(10);

        return response()->json([
            'success' => true,
            'pagamentos' => $pagamentos
        ], 200);
    }

    public function filtroPagamentos(): JsonResponse
    {
        // Captura o filtro da requisição ou usa "hoje" como padrão
        $filtro = strtolower(request()->input('filtro', 'hoje'));

        // Data atual
        $hoje = Carbon::now()->format('Y-m-d');

        // Define o intervalo de datas com base no filtro
        switch ($filtro) {
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

        // Verifica se é o filtro "hoje" ou "ontem" para usar whereDate
        if (in_array($filtro, ['hoje', 'ontem'])) {
            $data = $filtro === 'hoje' ? $hoje : Carbon::yesterday()->format('Y-m-d');

            $qtd = Payment::where('user_id', Auth::id())
                ->whereDate('created_at', $data)
                ->count();

            $Recebidos = Payment::where('user_id', Auth::id())
                ->where('status', 'Pago')
                ->whereDate('data_pagamento', $data)
                ->sum('valor_debito');

            $pendente = Payment::where('user_id', Auth::id())
                ->where('status', 'Pendente')
                ->whereDate('data_criado', $data)
                ->sum('valor_debito');
        } else {
            // Consulta para os demais filtros (semanal, mensal, anual)
            $qtd = Payment::where('user_id', Auth::id())
                ->whereBetween('created_at', [$inicio, $fim])
                ->count();

            $Recebidos = Payment::where('user_id', Auth::id())
                ->where('status', 'Pago')
                ->whereBetween('data_pagamento', [$inicio, $fim])
                ->sum('valor_debito');

            $pendente = Payment::where('user_id', Auth::id())
                ->where('status', 'Pendente')
                ->whereBetween('data_criado', [$inicio, $fim])
                ->sum('valor_debito');
        }

        return response()->json([
            'success' => true,
            'filtro' => ucfirst($filtro ?? 'hoje'),
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
        ], 200);
    }


}
