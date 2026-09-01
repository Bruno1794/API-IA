<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KirogoService
{

    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.kirago.url');
        $this->baseUser = config('services.kirago.user');
    }

    public function criarSessaoKira($dados)
    {
        // ==========================================
        // 1. CONSULTA AS INSTÂNCIAS EXISTENTES
        // ==========================================

        $usersResponse = Http::withToken($this->baseUser)
            ->acceptJson()
            ->get("{$this->baseUrl}/admin/users");

        if (!$usersResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível consultar as instâncias.',
                'status' => $usersResponse->status(),
                'data' => $usersResponse->json(),
            ], $usersResponse->status());
        }

        $usersData = $usersResponse->json();

        // Lista de usuários
        $users = $usersData['data'] ?? [];

        // ==========================================
        // 2. PROCURA PELO NOME DA INSTÂNCIA
        // ==========================================

        $usuarioExistente = collect($users)->first(function ($user) use ($dados) {
            return ($user['name'] ?? null) === $dados;
        });

        // ==========================================
        // 3. SE JÁ EXISTE, USA O TOKEN EXISTENTE
        // ==========================================

        if ($usuarioExistente) {

            $sessionToken = $usuarioExistente['token'];

        } else {

            // ==========================================
            // 4. NÃO EXISTE → GERA TOKEN NOVO
            // ==========================================

            $sessionToken = Str::random(40);

            // ==========================================
            // 5. CRIA NOVA INSTÂNCIA
            // ==========================================

            $createResponse = Http::withToken($this->baseUser)
                ->acceptJson()
                ->post("{$this->baseUrl}/admin/users", [
                    'name' => $dados,
                    'token' => $sessionToken,
                    'webhook' => 'https://teste.com/webhook',
                    'events' => 'All',
                ]);

            if (!$createResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível criar a instância.',
                    'status' => $createResponse->status(),
                    'data' => $createResponse->json(),
                ], $createResponse->status());
            }
        }

        // ==========================================
        // 6. A PARTIR DAQUI USA TOKEN DA INSTÂNCIA
        // ==========================================

        $sessionHttp = Http::withToken($sessionToken)
            ->acceptJson();

        // ==========================================
        // 7. CONNECT
        // ==========================================

        $connectResponse = $sessionHttp->post(
            "{$this->baseUrl}/session/connect",
            [
                'Subscribe' => [
                    'Message',
                    'ChatPresence',
                ],
                'Immediate' => true,
            ]
        );

        if (!$connectResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível conectar a sessão.',
                'status' => $connectResponse->status(),
                'data' => $connectResponse->json(),
            ], $connectResponse->status());
        }

        // ==========================================
        // 8. BUSCA QR CODE
        // ==========================================

        $qrResponse = $sessionHttp->get(
            "{$this->baseUrl}/session/qr"
        );

        if (!$qrResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível obter o QR Code.',
                'status' => $qrResponse->status(),
                'data' => $qrResponse->json(),
            ], $qrResponse->status());
        }

        $qrData = $qrResponse->json();

        // ==========================================
        // 9. RETORNA QR CODE
        // ==========================================

        return response()->json([
            'success' => true,
            'name' => $dados,
            'token' => $sessionToken,
            'qrcode' => $qrData['data']['QRCode'] ?? null,
        ]);
    }

    public function enviarMensagem(array $dados): array
    {
        // ==========================================
        // 0. VALIDAÇÕES
        // ==========================================

        if (empty($dados['token'])) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Nome da instância não informado.',
                'data' => null,
            ];
        }

        if (empty($dados['phone_cliente'])) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Telefone do cliente não informado.',
                'data' => null,
            ];
        }

        if (empty($dados['message'])) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Mensagem não informada.',
                'data' => null,
            ];
        }


        // ==========================================
        // 1. IDENTIFICADOR DA INSTÂNCIA
        // ==========================================

        $username = $dados['token'];


        // ==========================================
        // 2. BUSCA TODAS AS INSTÂNCIAS
        //    TOKEN ADMIN
        // ==========================================

        try {

            $usersResponse = Http::withToken($this->baseUser)
                ->acceptJson()
                ->get("{$this->baseUrl}/admin/users");

        } catch (\Throwable $e) {

            return [
                'success' => false,
                'status' => 500,
                'message' => 'Erro ao consultar as instâncias.',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }


        if (!$usersResponse->successful()) {

            return [
                'success' => false,
                'status' => $usersResponse->status(),
                'message' => 'Não foi possível consultar as instâncias.',
                'data' => $usersResponse->json(),
            ];
        }


        $users = $usersResponse->json('data', []);


        // ==========================================
        // 3. LOCALIZA A INSTÂNCIA PELO NOME
        // ==========================================

        $instancia = collect($users)->first(
            function ($user) use ($username) {

                return ($user['name'] ?? null) === $username;
            }
        );


        if (!$instancia) {

            return [
                'success' => false,
                'status' => 404,
                'message' => 'Instância não encontrada.',
                'data' => [
                    'instance' => $username,
                ],
            ];
        }


        // ==========================================
        // 4. TOKEN DA INSTÂNCIA
        // ==========================================

        $sessionToken = $instancia['token'] ?? null;


        if (!$sessionToken) {

            return [
                'success' => false,
                'status' => 500,
                'message' => 'A instância não possui token.',
                'data' => null,
            ];
        }


        // ==========================================
        // 5. NORMALIZA TELEFONE
        // ==========================================

        $phone = preg_replace(
            '/\D/',
            '',
            (string) $dados['phone_cliente']
        );


        // ==========================================
        // 6. ENVIA TEXTO PELO KIRAGO
        // ==========================================

        try {

            $response = Http::withToken($sessionToken)
                ->acceptJson()
                ->post(
                    "{$this->baseUrl}/chat/send/text",
                    [
                        'Phone' => $phone,
                        'Body' => $dados['message'],
                    ]
                );

        } catch (\Throwable $e) {

            return [
                'success' => false,
                'status' => 500,
                'message' => 'Erro de comunicação com o Kirago.',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }


        // ==========================================
        // 7. RETORNO
        // ==========================================

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'message' => $response->successful()
                ? 'Mensagem enviada com sucesso.'
                : 'Kirago não conseguiu enviar a mensagem.',
            'data' => $response->json(),
        ];
    }

    public function enviarPIX($dados)
    {
        // ==========================================
        // 0. VALIDA DADOS
        // ==========================================

        if (empty($dados['token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Token/nome da instância não informado.',
            ], 422);
        }

        if (empty($dados['phone_cliente'])) {
            return response()->json([
                'success' => false,
                'message' => 'Telefone do cliente não informado.',
            ], 422);
        }

        if (empty($dados['copy_code'])) {
            return response()->json([
                'success' => false,
                'message' => 'Código PIX não informado.',
            ], 422);
        }

        if (!isset($dados['valor'])) {
            return response()->json([
                'success' => false,
                'message' => 'Valor do PIX não informado.',
            ], 422);
        }


        // ==========================================
        // 1. IDENTIFICADOR DA INSTÂNCIA
        // ==========================================

        $username = $dados['token'];


        // ==========================================
        // 2. BUSCA AS INSTÂNCIAS
        //    USA TOKEN ADMIN
        // ==========================================

        $usersResponse = Http::withToken($this->baseUser)
            ->acceptJson()
            ->get("{$this->baseUrl}/admin/users");


        if (!$usersResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível consultar as instâncias.',
                'status' => $usersResponse->status(),
                'data' => $usersResponse->json(),
            ], $usersResponse->status());
        }


        $users = $usersResponse->json('data', []);


        // ==========================================
        // 3. ENCONTRA A INSTÂNCIA
        // ==========================================

        $instancia = collect($users)->first(function ($user) use ($username) {
            return ($user['name'] ?? null) === $username;
        });


        if (!$instancia) {
            return response()->json([
                'success' => false,
                'message' => 'Instância não encontrada.',
                'instance' => $username,
            ], 404);
        }


        // ==========================================
        // 4. TOKEN DA INSTÂNCIA
        // ==========================================

        $sessionToken = $instancia['token'] ?? null;


        if (!$sessionToken) {
            return response()->json([
                'success' => false,
                'message' => 'A instância não possui token.',
            ], 500);
        }


        // ==========================================
        // 5. NORMALIZA TELEFONE
        // ==========================================

        $phone = preg_replace(
            '/\D/',
            '',
            (string)$dados['phone_cliente']
        );


        // ==========================================
        // 6. FORMATA VALOR
        // ==========================================

        $valorFormatado = number_format(
            (float)$dados['valor'],
            2,
            ',',
            '.'
        );


        // ==========================================
        // 7. ENVIA BOTÃO PIX
        //    USA TOKEN DA INSTÂNCIA
        // ==========================================

        $response = Http::withToken($sessionToken)
            ->acceptJson()
            ->post("{$this->baseUrl}/chat/send/buttons", [

                'phone' => $phone,

                'title' => '*GATEBRIDGE SETTLE LTDA*',

                'body' =>
                    "⚠️ ATENÇÃO: Esta chave PIX é válida por 24 horas. Não reutilize após esse período.!\n\n"
                    . "💰 Valor: R$ {$valorFormatado}\n\n"
                    . "🔑 Clique no botão abaixo para copiar a chave PIX.
                    ",

                'buttons' => [
                    [
                        'name' => 'cta_copy',

                        'buttonParamsJson' => [
                            'display_text' => 'Copiar Chave PIX',
                            'copy_code' => $dados['copy_code'],
                        ],
                    ],
                ],
            ]);


        // ==========================================
        // 8. RETORNO
        // ==========================================

        return response()->json([
            'success' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->json(),
        ], $response->status());
    }
}
