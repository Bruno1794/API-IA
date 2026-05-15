<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class fastDepixService
{

    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.fastdepix.urlDepix');
        $this->baseToken = config('services.fastdepix.tokenDepix');

    }


    /*   public function statusService($token)
       {
           $response = Http::withHeaders([
               'X-QUEPASA-TOKEN' => $token
           ])
               ->withQueryParameters([
                   'action' => "status"
               ])
               ->get("{$this->baseUrl}/health");

           return $response->json('state');
       }*/


    public function gerarTransction($data)
    {
        try {
            // Envia a requisição com cabeçalho e timeout de segurança contra travamentos
            $response = Http::withHeaders([
                'Authorization' => "Bearer $this->baseToken",
            ])
                ->timeout(15) // Evita que sua aplicação trave se a API externa cair
                ->post("{$this->baseUrl}/transactions", [
                    "amount" => $data['amount'],
                    // Garante o fallback seguro caso o nome não venha preenchido
                    "user" => [
                        'name' => $data['user']['name'] ?? 'Consumidor',
                        'user_type' => 'individual'
                    ],
                    "payer_phone" => $data['payer_phone'],
                    "notification_url" => "https://api.codeacode.com.br/api/webhooks/fastdepix",
                ]);

            // Se a resposta for sucesso (status 2xx), retorna o JSON direto
            if ($response->successful()) {
                return $response->json();
            }

            // Caso a API retorne um erro (ex: 400 ou 500), trata a resposta de forma limpa
            return [
                'success' => false,
                'message' => 'Erro retornado pelo gateway de pagamento.',
                'status_code' => $response->status(),
                'error_details' => $response->json()
            ];

        } catch (\Exception $e) {
            // Captura falhas de rede, queda de servidor ou DNS inválido
            return [
                'success' => false,
                'message' => 'Não foi possível conectar ao servidor de pagamento.',
                'error' => $e->getMessage()
            ];
        }
    }

}
