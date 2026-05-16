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


    public function listTransctions($paramentro)
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer $this->baseToken"
        ])
            ->withQueryParameters([
                'status' => $paramentro['status'] ?? null,
                'date_from' => $paramentro['date_from'] ?? null,
                'date_to' => $paramentro['date_to'] ?? null,
                'search' => $paramentro['search'] ?? null,
                'page' => $paramentro['page'] ?? 1,
                'per_page' => $paramentro['per_page'] ?? 10,
            ])
            ->get("{$this->baseUrl}/transactions");

        return $response->json();
    }

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
