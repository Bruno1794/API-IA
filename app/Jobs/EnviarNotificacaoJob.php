<?php

namespace App\Jobs;

use App\Models\Client;
use app\Services\QuepasaService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnviarNotificacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $cliente;

    /**
     * Create a new job instance.
     */
    public function __construct($cliente)
    {
        //
        $this->cliente = $cliente;
    }

    /**
     * Execute the job.
     */
    public function handle(QuepasaService $quepasa)
    {
        $cliente = Client::with([
            'user:id,phone,username',
            'user.notices:id,user_id,day,message',
            'user.settings:id,user_id,notificar'
        ])->find($this->cliente->id);

        if (!$cliente) {
            return;
        }

        $diasDesativado = Carbon::parse($cliente->date_desativado)->diffInDays(now());

        $setting = $cliente->user->settings;
        if (!$setting || !$setting->notificar) {
            return;
        }

        foreach ($cliente->user->notices as $notice) {
            if ((int)$diasDesativado === (int)$notice->day) {
                $substituicoes = [
                    '[nome]' => $cliente->name,
                    '[vencimento]' => Carbon::parse($cliente->vencimento)->format('d/m/Y'),
                    '[telefone]' => $cliente->phone,
                    '[tipo_cobranca]' => $cliente->type_cobranca,
                    '[valor]' => $cliente->value_mensalidade,
                ];

                $mensagem = str_replace(
                    array_keys($substituicoes),
                    array_values($substituicoes),
                    $notice->message
                );

                $data = [
                    'phone_cliente' => $cliente->phone,
                    'message' => $mensagem,
                    'token' => $cliente->user->username,
                ];

                $quepasa->sendTextService($data);
            }
        }
    }

}
