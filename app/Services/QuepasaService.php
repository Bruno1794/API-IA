<?php

namespace app\Services;

use Illuminate\Support\Facades\Http;

class QuepasaService
{

    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.quepasa.url');
        $this->baseUser = config('services.quepasa.user');
    }

    public function gerarQrcodeService($token)
    {
        $response = Http::withHeaders([
            'X-QUEPASA-USER' => $this->baseUser,
            'X-QUEPASA-TOKEN' => $token,
        ])
            ->get("{$this->baseUrl}/scan");


        if ($response->successful()) {
            $imagemBinaria = $response->body();

            // Codifica em base64
            $base64 = base64_encode($imagemBinaria);

            // (Opcional) Adiciona o prefixo para exibir em <img src="...">
            $base64ComPrefixo = 'data:image/png;base64,' . $base64;
            return $base64ComPrefixo;
        }

        return null;
    }

    public function statusService($token)
    {
        $response = Http::withHeaders([
            'X-QUEPASA-TOKEN' => $token
        ])
            ->withQueryParameters([
                'action' => "status"
            ])
            ->get("{$this->baseUrl}/health");

        return $response->json('state');
    }

    public function chatIdConversa(array $dados): ?string
    {
        $token = $dados['token'] ?? null;
        $phone = $dados['phone'] ?? null;

        if (!$token || !$phone) {
            \Log::warning('Dados inválidos para buscar LID', [
                'phone' => $phone,
                'token' => $token,
            ]);

            return null;
        }

        $phone = preg_replace('/\D/', '', $phone);

        $tentativas = [
            $phone,
            '+' . $phone,
        ];

        // Se for Brasil com nono dígito: 55 + DDD + 9 + 8 dígitos
        if (preg_match('/^55\d{2}9\d{8}$/', $phone)) {
            $phoneSemNove = preg_replace('/^(55\d{2})9(\d{8})$/', '$1$2', $phone);

            $tentativas[] = $phoneSemNove;
            $tentativas[] = '+' . $phoneSemNove;
        }

        $tentativas = array_unique($tentativas);

        foreach ($tentativas as $phoneTentativa) {
            $lid = $this->buscarLid($phoneTentativa, $token);

            if ($lid && str_contains($lid, '@lid')) {
                return $lid;
            }
        }

        \Log::warning('LID não encontrado para telefone', [
            'phone' => $phone,
            'tentativas' => $tentativas,
            'token' => $token,
        ]);

        return null;
    }

    private function buscarLid(string $phone, string $token): ?string
    {
        try {
            $response = Http::withHeaders([
                'X-QUEPASA-TOKEN' => $token,
            ])
                ->timeout(10)
                ->withQueryParameters([
                    'phone' => $phone,
                ])
                ->get("{$this->baseUrl}/useridentifier");

            if (!$response->successful()) {
                \Log::warning('Erro ao buscar LID no Quepasa', [
                    'phone' => $phone,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $lid = $response->json('lid');

            return $lid && str_contains($lid, '@lid') ? $lid : null;
        } catch (\Throwable $e) {
            \Log::error('Exceção ao buscar LID no Quepasa', [
                'phone' => $phone,
                'erro' => $e->getMessage(),
            ]);

            return null;
        }
    }    public function webhookService($token)
    {
        $urls = [
            "http://n8npay.zapto.org:5678/webhook/6149f2e4-b726-4592-83d0-21db5f120de8",
            "http://n8npay.zapto.org:5678/webhook-test/6149f2e4-b726-4592-83d0-21db5f120de8",
        ];

        $response = Http::withHeaders([
            'Accept' => "application/json"
        ])
            ->post("{$this->baseUrl}/v3/bot/" . $token . "/webhook", [
                "url" => "http://n8npay.zapto.org:5678/webhook/6149f2e4-b726-4592-83d0-21db5f120de8",
                //"url" => "http://192.168.0.220:5678/webhook-test/8a78b727-2eb5-4cc4-8b8b-fbda6afcd024",
                "forwardinternal" => false,
            ],);


        return $response->json('status');
    }

    public function sendTextService($data)
    {
        $response = Http::withHeaders([
            'Accept' => "application/json"
        ])
            ->post("{$this->baseUrl}/v3/bot/" . $data['token'] . "/sendtext", [
                "chatId" => $data['phone_cliente'],
                "text" => $data['message'],
            ]);

        return $response->json('status');
    }
}
