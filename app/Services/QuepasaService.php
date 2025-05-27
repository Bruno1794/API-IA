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
            ->get("{$this->baseUrl}/command");

        return $response->json('status');
    }

    public function webhookService($token)
    {
        $urls = [
            "http://n8npay.zapto.org:5678/webhook/6149f2e4-b726-4592-83d0-21db5f120de8",
            "http://n8npay.zapto.org:5678/webhook-test/6149f2e4-b726-4592-83d0-21db5f120de8",
        ];

        $response = Http::withHeaders([
            'Accept' => "application/json"
        ])
            ->post("{$this->baseUrl}/v3/bot/" . $token . "/webhook", [
                //"url" => "http://n8npay.zapto.org:5678/webhook/6149f2e4-b726-4592-83d0-21db5f120de8",
                "url" => "http://n8npay.zapto.org:5678/webhook/6149f2e4-b726-4592-83d0-21db5f120de8",
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
                "chatid" => $data['phone_cliente'],
                "text" => $data['message'],
            ]);

        return $response->json('status');
    }
}
