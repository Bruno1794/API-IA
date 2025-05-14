<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Settings;
use App\Services\QuepasaService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class EnviarMensagemWhatsApp implements ShouldQueue
{
    use  Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    /**
     * Create a new job instance.
     */
    public function __construct(public int $clienteId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(QuepasaService $quepasa)
    {
        $cliente = Client::with('user')->find($this->clienteId);
        $config = Settings::where('user_id', $cliente->user_id)->first();
        $dados = [
            'message' => $config->msg_padrao ?? $cliente->msg_enviar,
            'phone_cliente' => $cliente->phone,
            'token' => $cliente->user->username,
        ];
        $quepasa->sendTextService($dados);

        // Usa o vencimento atual vindo do banco
        $vencimentoAtual = Carbon::parse($cliente->vencimento);

        // Calcula o novo vencimento de acordo com o tipo de cobrança
        switch ($cliente->type_cobranca) {
            case 'MENSAL':
                $novoVencimento = $vencimentoAtual->addMonth();
                break;

            case 'BIMESTRAL':
                $novoVencimento = $vencimentoAtual->addMonth(2);
                break;

            case 'TRIMESTRAL':
                $novoVencimento = $vencimentoAtual->addMonth(3);
                break;

            case 'SEMESTRAL':
                $novoVencimento = $vencimentoAtual->addMonths(6);
                break;

            case 'ANUAL':
                $novoVencimento = $vencimentoAtual->addYear();
                break;
            default:
                $novoVencimento = $vencimentoAtual; // Mantém o atual, ou trate erro
        }


        $cliente->update([
            'vencimento' => $novoVencimento
        ]);

        $cliente->payments()->create([
            'user_id' => $cliente->user_id,
            'data_criado' => Carbon::today()->toDateString(),
            'valor_debito' => $cliente->value_mensalidade,
            'tipo_pagamento' => $cliente->preferencia,
        ]);
    }


}
##php artisan queue:work
