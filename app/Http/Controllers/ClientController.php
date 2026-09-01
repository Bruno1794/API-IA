<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarMensagemWhatsApp;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use App\Services\fastDepixService;
use App\Services\KirogoService;
use App\Services\QuepasaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    //
    protected $quepasa;
    protected $kirago;
    protected $fastDepix;

    public function __construct(QuepasaService $quepasa, FastDepixService $fastDepix, KirogoService $kirogo)
    {
        $this->quepasa = $quepasa;
        $this->fastDepix = $fastDepix;
        $this->kirago = $kirogo;
    }

//   ->when($search, function ($query, $search) {
//                $query->where(function ($query) use ($search) {
//                    $query->where('name', 'LIKE', "%{$search}%")
//                        ->orWhere('referencia', 'LIKE', "%{$search}%");
//                });
//            })
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $search = $request->input('pesquisa');
        $filtro = $request->input('filtro');

        $clientes = Client::where([
            ['user_id', '=', $user->id],
            ['status', '=', $filtro],
            ['cobrar', '=', '0'],
        ])
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('referencia', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%");
                });
            })
            ->orderBy('vencimento')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'clientes' => $clientes
        ], 200);
    }


    public function listaCliente(Request $request): JsonResponse
    {
        $apiKey = $request->header('X-API-KEY');

        if ($apiKey !== env('CLIENTES_API_KEY')) {
            return response()->json([
                'success' => false,
                'message' => 'Não autorizado'
            ], 401);
        }

        $clientes = Client::select('id', 'name', 'referencia', 'phone','vencimento','status')->get();

        return response()->json([
            'success' => true,
            'clientes' => $clientes
        ]);
    }


    public function listCont(): JsonResponse
    {
        $filtro = strtolower(request()->input('filtro', 'hoje'));
        $hoje = Carbon::now();  // Data e hora atual

        // Define o intervalo de datas com base no filtro
        switch ($filtro) {
            case 'ontem':
                $inicio = Carbon::yesterday()->startOfDay();
                $fim = Carbon::yesterday()->endOfDay();
                break;

            case 'semanal':
                $inicio = Carbon::now()->startOfWeek();
                $fim = Carbon::now()->endOfWeek();
                break;

            case 'mensal':
                $inicio = Carbon::now()->startOfMonth();
                $fim = Carbon::now()->endOfMonth();
                break;

            case 'anual':
                $inicio = Carbon::now()->startOfYear();
                $fim = Carbon::now()->endOfYear();
                break;

            case 'hoje':
            default:
                $inicio = Carbon::now()->startOfDay();
                $fim = Carbon::now()->endOfDay();
                break;
        }

        // Contagem de clientes novos (zero para semanal, mensal e anual)
        if (in_array($filtro, ['semanal', 'mensal', 'anual'])) {
            $clientesNovos = 0;
        } else {
            $clientesNovos = Client::where('user_id', Auth::id())
                ->where('status', 'Ativo')
                ->whereBetween('created_at', [$inicio, $fim])
                ->count();
        }

        // Contagem de clientes ativos (clientes criados antes do dia atual e com status ATIVO)
        $clientesAtivos = Client::where('user_id', Auth::id())
            ->where('status', 'Ativo')
            ->whereDate('created_at', '<', Carbon::now()->startOfDay())
            ->count();

        // Contagem de clientes inativos
        $clientesInativos = Client::where('user_id', Auth::id())
            ->where('status', 'Inativo')
            ->count();

        return response()->json([
            'success' => true,
            'filtro' => ucfirst($filtro),
            'clientes' => [
                'novos' => $clientesNovos,
                'ativos' => $clientesAtivos,
                'inativos' => $clientesInativos,
            ],
        ]);
    }


    public function listClientNew(Request $request): JsonResponse
    {
        $user = Auth::user();

        $clientes = Client::where([
            ['user_id', '=', $user->id],
            ['status', '=', 'Novo'],
            ['cobrar', '=', '0'],
        ])
            ->get();
        //->paginate(10);

        return response()->json([
            'success' => true,
            'clientes' => $clientes
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $userLogado = Auth::user();
        $avisarDias = (int)($request->avisar ?? 0);
        $mensagem = sprintf(
            'Bom dia, *%s* seu plano %s expira %s. Queria saber se tem interesse em renovar?',
            $request->name,
            $request->type_cobranca,
            $avisarDias ? "em {$avisarDias} dias" : "hoje"
        );

        $client = Client::create([
            'name' => $request->name,
            'phone' => '55' . $request->phone,
            'email' => $request->email,
            'cpf' => $request->cpf,
            'cobrar' => filter_var($request->cobrar, FILTER_VALIDATE_BOOLEAN),
            'avisar' => $avisarDias,
            'vencimento' => $request->vencimento,
            'value_mensalidade' => $request->value_mensalidade,
            'msg_enviar' => $mensagem,
            'type_cobranca' => $request->type_cobranca,
            'referencia' => $request->referencia,
            'observation' => $request->observation,
            'preferencia' => $request->preferencia,
            'user_id' => $userLogado->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $client,
        ]);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $userLogado = Auth::user();

        // Verifica se o cliente pertence ao usuário logado
        if ($client->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }

        $avisarDias = (int)($request->avisar ?? 0);

        $client->update([
            'name' => $request->name,
            'phone' => '55' . $request->phone,
            'email' => $request->email,
            'cpf' => $request->cpf,
            'cobrar' => filter_var($request->cobrar, FILTER_VALIDATE_BOOLEAN),
            'avisar' => $avisarDias,
            'vencimento' => $request->vencimento,
            'value_mensalidade' => $request->value_mensalidade,
            'msg_enviar' => $request->msg_enviar,
            'type_cobranca' => $request->type_cobranca,
            'referencia' => $request->referencia,
            'observation' => $request->observation,
            'preferencia' => $request->preferencia,
            'user_id' => $userLogado->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $client,
        ]);
    }

    public function AtivaClient(Request $request, Client $client): JsonResponse
    {
        $userLogado = Auth::user();

        // Verifica se o cliente pertence ao usuário logado
        if ($client->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }

        $avisarDias = (int)($request->avisar ?? 0);
        $mensagem = sprintf(
            'Bom dia, *%s* seu plano expira %s. Queria saber se tem interesse em renovar?',
            $request->name,
            $avisarDias ? "em {$avisarDias} dias" : "hoje"
        );

        $client->update([
            'name' => $request->name,
            'referencia' => $request->referencia,
            'email' => $request->email,
            'cpf' => $request->cpf,
            'cobrar' => "0",
            'avisar' => $avisarDias,
            'vencimento' => $request->vencimento,
            'value_mensalidade' => $request->value_mensalidade,
            'msg_enviar' => $mensagem,
            'status' => "Ativo",
            'type_cobranca' => $request->type_cobranca,
            'user_id' => $userLogado->id,
        ]);

        $client->payments()->create([
            'user_id' => Auth::id(),
            'data_criado' => Carbon::today()->toDateString(),
            'valor_debito' => $client->value_mensalidade,
            'status' => "PAGO",
            'data_pagamento' => carbon::now(),
        ]);
        return response()->json([
            'success' => true,
            'data' => $client,
        ]);
    }

    public function UpdateStatus(Request $request, Client $client): JsonResponse
    {
        $userLogado = Auth::user();

        // Verifica se o cliente pertence ao usuário logado
        if ($client->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }


        // Atualiza o status e demais campos
        $client->update([
            'status' => $request->status,
            'vencimento' => $request->vencimento ?? $client->vencimento,
            'value_mensalidade' => $request->value_mensalidade ?? $client->value_mensalidade,
            'type_cobranca' => $request->type_cobranca ?? $client->type_cobranca,
            "date_desativado" => $request->status === "Inativo" || $request->status === "Cancelado" ? carbon::now() : null,
        ]);


        // Verifica se precisa gerar cobrança
        if ($request->gerar_cobranca) {
            $client->payments()->create([
                'user_id' => Auth::id(),

                'valor_debito' => $request->value_mensalidade ?? $client->value_mensalidade,
                'data_criado' => Carbon::now()->toDateString(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $client,
        ]);
    }

    public function UpdateTypeClient(Request $request, Client $client): JsonResponse
    {
        $userLogado = Auth::user();

        // Verifica se o cliente pertence ao usuário logado
        if ($client->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }

        $client->update([
            'type_client' => $request->type_client,

        ]);

        return response()->json([
            'success' => true,
            'data' => $client,
        ]);
    }
    public function fastDepix(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required'],
            'value_cobranca' => ['required', 'numeric', 'min:0.01'],
        ]);

        /*
         * Normaliza telefone.
         */
        $phone = preg_replace(
            '/\D/',
            '',
            (string) $request->phone
        );

        if (!str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        }

        /*
         * Se vier com 12 dígitos:
         * 55 + DDD + 8 dígitos
         * adiciona o 9.
         */
        if (strlen($phone) === 12) {
            $phone =
                substr($phone, 0, 4)
                . '9'
                . substr($phone, 4);
        }

        $valor = (float) $request->value_cobranca;

        $cliente = Client::where(
            'phone',
            $phone
        )->first();


        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não encontrado',
            ], 404);
        }

        try {

            $pix = $this->gerarPixCliente(
                $cliente,
                $valor
            );

            return response()->json([
                'success' => true,
                'reused' => $pix['reused'],
                'valor' => $pix['valor'],
                'copy_code' => $pix['copy_code'],
                'dadosDepix' => $pix['dadosDepix'],
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar PIX.',
            ], 500);
        }
    }

    /**
     * Gera ou reaproveita um PIX FastDepix para o cliente.
     */
    private function gerarPixCliente(Client $cliente, float $valor): array
    {
        $phone = preg_replace('/\D/', '', $cliente->phone);

        $cacheKey = "fastdepix_pix_{$phone}_{$valor}";

        $pixExistente = Cache::get($cacheKey);

        // ==========================================
        // 1. TENTA REAPROVEITAR PIX EXISTENTE
        // ==========================================
        if ($pixExistente) {

            $dadosDepix = data_get(
                $pixExistente,
                'dadosDepix'
            );

            $depixId =
                data_get(
                    $dadosDepix,
                    'data.depix_transaction_id'
                )
                ?? data_get(
                $dadosDepix,
                'data.provider_transaction_id'
            )
                ?? data_get(
                    $dadosDepix,
                    'data.id'
                );

            $statusCache = null;

            if ($depixId) {
                $statusCache = Cache::get(
                    "fastdepix_status_{$depixId}"
                );
            }

            $status =
                data_get($statusCache, 'status')
                ?? data_get($dadosDepix, 'data.status')
                ?? data_get($dadosDepix, 'data.provider_status');

            if (
                !in_array(
                    $status,
                    [
                        'paid',
                        'expired',
                        'transaction.paid',
                        'transaction.expired',
                    ],
                    true
                )
            ) {

                /*
                 * ESTE É O CAMPO REAL DA FASTFLOW
                 */
                $copyCode =
                    data_get($dadosDepix, 'data.qr_code_text')
                    ?? data_get($dadosDepix, 'data.pix_code')
                    ?? data_get($dadosDepix, 'data.pixCode')
                    ?? data_get($dadosDepix, 'data.copy_code')
                    ?? data_get($dadosDepix, 'data.copyCode')
                    ?? data_get($dadosDepix, 'qr_code_text');

                return [
                    'success' => true,
                    'reused' => true,
                    'valor' => $valor,
                    'copy_code' => $copyCode,
                    'dadosDepix' => $dadosDepix,
                ];
            }
        }

        // ==========================================
        // 2. GERA UM PIX NOVO
        // ==========================================

        $dados = [
            'amount' => $valor,

            'user' => [
                'name' =>
                    $cliente->referencia
                        ?: $cliente->name,

                'user_type' =>
                    'individual',
            ],

            'payer_phone' => $phone,

            'notification_url' =>
                'https://api.codeacode.com.br/api/webhooks/fastdepix',
        ];

        $dadosDepix =
            $this->fastDepix->gerarTransction(
                $dados
            );

        // ==========================================
        // 3. PEGA O PIX COPIA E COLA
        // ==========================================

        $copyCode =
            data_get(
                $dadosDepix,
                'data.qr_code_text'
            )
            ?? data_get(
            $dadosDepix,
            'data.pix_code'
        )
            ?? data_get(
            $dadosDepix,
            'data.pixCode'
        )
            ?? data_get(
            $dadosDepix,
            'data.copy_code'
        )
            ?? data_get(
            $dadosDepix,
            'data.copyCode'
        )
            ?? data_get(
                $dadosDepix,
                'qr_code_text'
            );

        // ==========================================
        // 4. CACHE 20 MINUTOS
        // ==========================================

        Cache::put(
            $cacheKey,
            [
                'dadosDepix' =>
                    $dadosDepix,
            ],
            now()->addMinutes(20)
        );

        return [
            'success' => true,
            'reused' => false,
            'valor' => $valor,
            'copy_code' => $copyCode,
            'dadosDepix' => $dadosDepix,
        ];
    }
    public function storeWhats(Request $request): JsonResponse
    {

        /*
         * Localiza o administrador pela instância/telefone.
         */
        $dadosAdmin = User::with('settings')
            ->where(
                'phone',
                Str::before(
                    (string) $request->phone,
                    ':'
                )
            )
            ->first();

        if (!$dadosAdmin) {
            return response()->json([
                'error' =>
                    'Usuário administrador não encontrado.',
            ], 404);
        }

        /*
         * Normaliza telefone do cliente.
         */
        $phone = preg_replace(
            '/\D/',
            '',
            Str::before(
                (string) $request->phone_cliente,
                '@'
            )
        );

        /*
         * Garante DDI 55.
         */
        if (!str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        }

        /*
         * Caso venha:
         *
         * 55 + DDD + 8 dígitos
         *
         * adiciona o nono dígito.
         */
        if (strlen($phone) === 12) {
            $phone =
                substr($phone, 0, 4)
                . '9'
                . substr($phone, 4);
        }

        /*
         * Validação final:
         *
         * 55
         * + DDD
         * + 9
         * + 8 dígitos
         */
        if (!preg_match(
            '/^55\d{2}9\d{8}$/',
            $phone
        )) {

            return response()->json([
                'error' =>
                    'Número de telefone do cliente não é válido.',
                'phone' => $phone,
            ], 422);
        }

        /*
         * Procura cliente existente.
         */
        $cliente = Client::where(
            'phone',
            $phone
        )->first();

        /*
         * Cria cliente automaticamente somente
         * se for mensagem de texto e o cadastro
         * automático estiver habilitado.
         */
        if (
            !$cliente
            && $request->type === 'Message'
            && $dadosAdmin->settings?->cadastro
        ) {

            $cliente = Client::create([
                'phone' => $phone,
                'user_id' => $dadosAdmin->id,
                'name' => $request->name,
                'status' => 'Novo',
            ]);
        }

        /*
         * Se ainda não existe cliente,
         * encerra aqui.
         */
        if (!$cliente) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Cliente não encontrado e cadastro automático desabilitado.',
            ], 404);
        }

        /*
         * Carrega usuário caso ainda não esteja carregado.
         */
        $cliente->loadMissing('user');

        /*
         * Automação de renovação.
         *
         * Exemplos aceitos:
         *
         * #30
         * #50
         * #79.90
         * #79,90
         */
        if (
            $cliente->status !== 'Novo'
            && !empty($request->renovar)
        ) {

            if (
                !preg_match(
                    '/^#(\d+(?:[.,]\d{1,2})?)$/',
                    trim($request->renovar),
                    $matches
                )
            ) {

                return response()->json([
                    'success' => false,
                    'error' =>
                        "Formato inválido: {$request->renovar}",
                ], 422);
            }

            /*
             * Remove o # e converte vírgula para ponto.
             */
            $valor = (float) str_replace(
                ',',
                '.',
                $matches[1]
            );

            if ($valor <= 0) {

                return response()->json([
                    'success' => false,
                    'error' =>
                        'Valor da cobrança inválido.',
                ], 422);
            }

            try {

                /*
                 * Gera ou reaproveita PIX.
                 */
                $pix = $this->gerarPixCliente(
                    $cliente,
                    $valor
                );

                $copyCode = $pix['copy_code'];

                /*
                 * Segurança:
                 * não tenta enviar sem copia e cola.
                 */
                if (!$copyCode) {

                    return response()->json([
                        'success' => false,
                        'message' =>
                            'PIX foi gerado, mas o código copia e cola não foi encontrado.',
                        'dadosDepix' =>
                            $pix['dadosDepix'] ?? null,
                    ], 500);
                }

                $valorFormatado =
                    number_format(
                        $valor,
                        2,
                        ',',
                        '.'
                    );

                /*
                 * Monta mensagem automática.
                 */
                $message =
                    "💳 *Renovação do serviço*\n\n"
                    . "Valor: *R$ {$valorFormatado}*\n\n"
                    . "📲 *PIX Copia e Cola:*\n\n"
                    . "{$copyCode}\n\n"
                    . "Após o pagamento, a confirmação acontece automaticamente ✅";

                /*
                 * Dados enviados para o Kirago.
                 */

                $dados = [
                    'phone_cliente' =>
                        $request->chatId,

                    'token' =>
                        $cliente->user->username,

                    'valor' =>
                        $valor,

                    'copy_code' =>
                        $copyCode,
                ];


                /*
                 * Envia pelo Kirago.
                 */
                $res = $this->kirago->enviarPIX(
                    $dados
                );

                return response()->json([
                    'success' => true,

                    'client' =>
                        $cliente,

                    'pix' => [
                        'reused' =>
                            $pix['reused'],

                        'valor' =>
                            $valor,

                        'copy_code' =>
                            $copyCode,
                    ],

                    'kirago' =>
                        $res,
                ], 200);

            } catch (\Throwable $e) {

                report($e);

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Erro ao gerar ou enviar a cobrança PIX.',
                ], 500);
            }
        }

        /*
         * Fluxo normal sem renovação.
         */
        return response()->json([
            'success' => true,
            'client' => $cliente,
        ], 200);
    }


    public function cobranca(): JsonResponse
    {
        $horaAtual = Carbon::now()->format('H:i');

        $enviadas = 0;
        $ignoradas = 0;
        $erros = 0;


        // ==========================================
        // 1. LOCALIZA CLIENTES PARA COBRANÇA
        // ==========================================

        $clientes = Client::where('status', 'Ativo')
            ->where('cobrar', false)
            ->where('is_processing', false)
            ->with([
                'user.settings'
            ])
            ->get()
            ->filter(function ($cliente) use ($horaAtual) {

                /*
                 * Precisa existir usuário e configurações.
                 */
                if (
                    !$cliente->user
                    || !$cliente->user->settings
                ) {
                    return false;
                }


                /*
                 * Aguarda chegar o horário configurado
                 * para cobrança.
                 */
                if (
                    $cliente->user->settings->time_cobranca
                    >= $horaAtual
                ) {
                    return false;
                }


                /*
                 * Verifica data da cobrança.
                 *
                 * Exemplo:
                 *
                 * vencimento = dia 10
                 * avisar = 2
                 *
                 * cobrança será feita dia 8.
                 */
                return Carbon::parse(
                    $cliente->vencimento
                )->isSameDay(
                    Carbon::today()->addDays(
                        $cliente->avisar ?? 0
                    )
                );
            });


        // ==========================================
        // 2. NENHUMA COBRANÇA
        // ==========================================

        if ($clientes->isEmpty()) {

            return response()->json([
                'success' => true,
                'message' =>
                    'Nenhuma cobrança a ser realizada no momento.',
                'total' => 0,
                'enviadas' => 0,
                'ignoradas' => 0,
                'erros' => 0,
            ], 200);
        }


        // ==========================================
        // 3. PROCESSA CLIENTES
        // ==========================================

        foreach ($clientes as $cliente) {

            /*
             * Lock simples.
             *
             * Evita duas execuções simultâneas
             * cobrarem o mesmo cliente.
             */
            $atualizado = Client::where(
                'id',
                $cliente->id
            )
                ->where(
                    'is_processing',
                    false
                )
                ->update([
                    'is_processing' => true
                ]);


            if ($atualizado === 0) {
                continue;
            }


            try {

                // ==========================================
                // 4. CONFERE USUÁRIO
                // ==========================================

                if (!$cliente->user) {

                    \Log::warning(
                        'Cliente sem usuário na cobrança',
                        [
                            'cliente_id' =>
                                $cliente->id,
                        ]
                    );

                    $ignoradas++;

                    continue;
                }


                if (!$cliente->user->username) {

                    \Log::warning(
                        'Usuário sem instância Kirago configurada',
                        [
                            'cliente_id' =>
                                $cliente->id,

                            'user_id' =>
                                $cliente->user_id,
                        ]
                    );

                    $ignoradas++;

                    continue;
                }


                // ==========================================
                // 5. CALCULA NOVO VENCIMENTO
                // ==========================================

                $vencimentoAtual = Carbon::parse(
                    $cliente->vencimento
                );


                $novoVencimento = match (
                $cliente->type_cobranca
                ) {

                    'BIMESTRAL' =>
                    $vencimentoAtual
                        ->copy()
                        ->addMonths(2),

                    'TRIMESTRAL' =>
                    $vencimentoAtual
                        ->copy()
                        ->addMonths(3),

                    'SEMESTRAL' =>
                    $vencimentoAtual
                        ->copy()
                        ->addMonths(6),

                    'ANUAL' =>
                    $vencimentoAtual
                        ->copy()
                        ->addYear(),

                    default =>
                    $vencimentoAtual
                        ->copy()
                        ->addMonth(),
                };


                // ==========================================
                // 6. MONTA MENSAGEM
                // ==========================================

                $mensagemPadrao =
                    $cliente->user
                        ->settings
                        ?->msg_padrao;


                $substituicoes = [

                    '[nome]' =>
                        $cliente->name,

                    '[vencimento]' =>
                        Carbon::parse(
                            $cliente->vencimento
                        )->format('d/m/Y'),

                    '[telefone]' =>
                        $cliente->phone,

                    '[tipo_cobranca]' =>
                        $cliente->type_cobranca,

                    '[valor]' =>
                        number_format(
                            (float) $cliente->value_mensalidade,
                            2,
                            ',',
                            '.'
                        ),
                ];


                if ($mensagemPadrao) {

                    $mensagem = str_replace(
                        array_keys($substituicoes),
                        array_values($substituicoes),
                        $mensagemPadrao
                    );

                } else {

                    $mensagem =
                        $cliente->msg_enviar;
                }


                // ==========================================
                // 7. SEM MENSAGEM
                // ==========================================

                if (!$mensagem) {

                    \Log::warning(
                        'Cliente sem mensagem de cobrança',
                        [
                            'cliente_id' =>
                                $cliente->id,
                        ]
                    );

                    $ignoradas++;

                    continue;
                }


                // ==========================================
                // 8. NORMALIZA TELEFONE
                // ==========================================

                $phone = preg_replace(
                    '/\D/',
                    '',
                    (string) $cliente->phone
                );


                if (!str_starts_with(
                    $phone,
                    '55'
                )) {
                    $phone = '55' . $phone;
                }


                // ==========================================
                // 9. DADOS PARA O KIRAGO
                // ==========================================

                $dados = [

                    'message' =>
                        $mensagem,

                    'phone_cliente' =>
                        $phone,

                    /*
                     * Aqui usamos o username como
                     * nome da instância Kirago.
                     */
                    'token' =>
                        $cliente->user->username,
                ];


                // ==========================================
                // 10. ENVIA PELO KIRAGO
                // ==========================================

                $retorno =
                    $this->kirago
                        ->enviarMensagem(
                            $dados
                        );


                // ==========================================
                // 11. CONFERE RESULTADO
                // ==========================================

                if (
                    !isset($retorno['success'])
                    || $retorno['success'] !== true
                ) {

                    \Log::error(
                        'Erro no envio da cobrança pelo Kirago',
                        [
                            'cliente_id' =>
                                $cliente->id,

                            'phone' =>
                                $phone,

                            'instancia' =>
                                $cliente->user->username,

                            'retorno' =>
                                $retorno,
                        ]
                    );

                    $erros++;

                    continue;
                }


                // ==========================================
                // 12. ATUALIZA VENCIMENTO
                // ==========================================

                $cliente->update([
                    'vencimento' =>
                        $novoVencimento,
                ]);


                // ==========================================
                // 13. CRIA FINANCEIRO
                // ==========================================

                $cliente->payments()->create([

                    'user_id' =>
                        $cliente->user_id,

                    'data_criado' =>
                        Carbon::today()
                            ->toDateString(),

                    'valor_debito' =>
                        $cliente->value_mensalidade,

                    'tipo_pagamento' =>
                        $cliente->preferencia,
                ]);


                // ==========================================
                // 14. SUCESSO
                // ==========================================

                $enviadas++;


                \Log::info(
                    'Cobrança enviada pelo Kirago',
                    [
                        'cliente_id' =>
                            $cliente->id,

                        'phone' =>
                            $phone,

                        'instancia' =>
                            $cliente->user->username,

                        'novo_vencimento' =>
                            $novoVencimento
                                ->format('Y-m-d'),
                    ]
                );


                /*
                 * Evita disparar mensagens muito rápido.
                 */
                sleep(5);

            } catch (\Throwable $e) {

                // ==========================================
                // ERRO
                // ==========================================

                \Log::error(
                    'Erro ao processar cobrança',
                    [
                        'cliente_id' =>
                            $cliente->id,

                        'phone' =>
                            $cliente->phone,

                        'erro' =>
                            $e->getMessage(),

                        'arquivo' =>
                            $e->getFile(),

                        'linha' =>
                            $e->getLine(),
                    ]
                );

                $erros++;

                continue;

            } finally {

                // ==========================================
                // SEMPRE LIBERA O CLIENTE
                // ==========================================

                Client::where(
                    'id',
                    $cliente->id
                )->update([
                    'is_processing' => false,
                ]);
            }
        }


        // ==========================================
        // 15. RETORNO
        // ==========================================

        return response()->json([
            'success' => true,
            'total' => $clientes->count(),
            'enviadas' => $enviadas,
            'ignoradas' => $ignoradas,
            'erros' => $erros,
        ], 200);
    }

    public function destroy(Client $client): JsonResponse
    {
        $userLogado = Auth::user();

        // Verifica se o cliente pertence ao usuário logado
        if ($client->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }
        $client->delete();
        return response()->json([
            'success' => true,
            'client' => $client
        ], 200);
    }

    public function show(Client $client): JsonResponse
    {
        $userLogado = Auth::user();

        // Verifica se o cliente pertence ao usuário logado
        if ($client->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }

        return response()->json([
            'success' => true,
            'client' => $client
        ], 200);
    }

    public function Renew(Request $request, Client $client): JsonResponse
    {
        $userLogado = Auth::user();
        if ($client->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }

        try {
            // Usa o vencimento atual vindo do banco
            $vencimentoAtual = Carbon::parse($client->vencimento);

            // Calcula o novo vencimento de acordo com o tipo de cobrança
            switch ($client->type_cobranca) {
                case 'MENSAL':
                    $novoVencimento = $vencimentoAtual->clone()->addMonth();
                    break;

                case 'BIMESTRAL':
                    $novoVencimento = $vencimentoAtual->clone()->addMonths(2);
                    break;

                case 'TRIMESTRAL':
                    $novoVencimento = $vencimentoAtual->clone()->addMonths(3);
                    break;

                case 'SEMESTRAL':
                    $novoVencimento = $vencimentoAtual->clone()->addMonths(6);
                    break;

                case 'ANUAL':
                    $novoVencimento = $vencimentoAtual->clone()->addYear();
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Tipo de cobrança inválido.',
                    ], 400); // código 400 = requisição inválida
            }

            // Atualiza o vencimento do cliente
            $client->update([
                'vencimento' => $novoVencimento
            ]);

            if ($request->gerar_cobranca) {
                // Cria o registro de pagamento
                $client->payments()->create([
                    'user_id' => Auth::id(),
                    'data_criado' => Carbon::today()->toDateString(),
                    'valor_debito' => $client->value_mensalidade,
                    'tipo_pagamento' => $client->preferencia,
                    'observation' => $request->observation,
                ]);
            }


            return response()->json([
                'success' => true,
                'message' => "Cliente Renovado",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar a cobrança: ' . $e->getMessage(),
            ], 500); // código 500 = erro interno do servidor
        }
    }

    public function payments(Client $client, Request $request): JsonResponse
    {
        $userLogado = Auth::user();
        $search = $request->input('pesquisa');
        if ($client->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }

        $pagamentos = Payment::where('client_id', $client->id)
            ->orderBy('data_criado', 'desc')
            ->orderByRaw("CASE WHEN status = 'pendente' THEN 0 ELSE 1 END")
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('data_criado', 'LIKE', "%{$search}%")
                        ->orWhere('status', 'LIKE', "%{$search}%")
                        ->orWhere('tipo_pagamento', 'LIKE', "%{$search}%")
                        ->orWhere('observation', 'LIKE', "%{$search}%")
                        ->orWhere('valor_debito', 'LIKE', "%{$search}%");
                });
            })
            ->paginate(10);

        return response()->json([
            'success' => true,
            'payments' => $pagamentos
        ], 200);
    }




    public function webhooks(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('FASTDEPIX WEBHOOK RECEBIDO', $payload);

        $transactionId =
            data_get($payload, 'provider_transaction_id') ??
            data_get($payload, 'data.provider_transaction_id') ??
            data_get($payload, 'depix_transaction_id') ??
            data_get($payload, 'data.depix_transaction_id') ??
            data_get($payload, 'external_ref') ??
            data_get($payload, 'data.external_ref') ??
            data_get($payload, 'transaction_id') ??
            data_get($payload, 'data.transaction_id') ??
            data_get($payload, 'id') ??
            data_get($payload, 'data.id');

        $status =
            data_get($payload, 'status') ??
            data_get($payload, 'data.status') ??
            data_get($payload, 'provider_status') ??
            data_get($payload, 'data.provider_status') ??
            data_get($payload, 'event');

        $event =
            data_get($payload, 'event') ??
            data_get($payload, 'type');

        if (!$transactionId) {
            Log::warning('FASTDEPIX WEBHOOK SEM IDENTIFICADOR', $payload);

            return response()->json([
                'success' => false,
                'message' => 'Transaction ID não encontrado',
            ], 400);
        }

        $statusNormalizado = strtolower(trim((string) $status));

        Cache::put(
            "fastdepix_status_{$transactionId}",
            [
                'status' => $statusNormalizado,
                'event' => $event,
                'transaction_id' => $transactionId,
                'payload' => $payload,
                'updated_at' => now()->toDateTimeString(),
            ],
            now()->addHours(26)
        );

        return response()->json([
            'success' => true,
            'message' => 'Webhook recebido',
            'status' => $statusNormalizado,
            'event' => $event,
            'transaction_id' => $transactionId,
        ]);
    }

    public function status($transactionId): JsonResponse
    {
        $status = Cache::get("fastdepix_status_{$transactionId}");

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    public function trasacitonFast(Request $request): JsonResponse
    {
        // =========================
        // FILTROS
        // =========================
        $dateFrom = $request->date_from
            ?? Carbon::now()->startOfMonth()->format('Y-m-d');

        $dateTo = $request->date_to
            ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        $status = $request->status ?? 'paid';

        $search = mb_strtolower(trim($request->search ?? ''));

        $currentPage = (int)($request->page ?? 1);

        $perPage = 10;

        // =========================
        // PARAMS BASE API
        // (SEM SEARCH)
        // =========================
        $baseParams = [
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'per_page' => $perPage,
        ];

        // =========================
        // BUSCA TODAS AS PÁGINAS
        // =========================
        $page = 1;
        $totalPages = 1;

        $allTransactions = collect();

        do {

            $params = $baseParams;
            $params['page'] = $page;

            $response = $this->fastDepix->listTransctions($params);

            $transactions = collect(
                $response['data']['transactions'] ?? []
            );

            if ($transactions->isEmpty()) {
                break;
            }

            $allTransactions = $allTransactions->merge($transactions);

            $totalPages = $response['data']['pagination']['total_pages'] ?? 1;

            $page++;

        } while ($page <= $totalPages);

        // =========================
        // FILTRO LOCAL DE PESQUISA
        // =========================
        if (!empty($search)) {

            $allTransactions = $allTransactions->filter(function ($item) use ($search) {

                $name = mb_strtolower($item['user']['name'] ?? '');

                $phone = mb_strtolower($item['payer_phone'] ?? '');

                $status = mb_strtolower($item['status'] ?? '');

                return str_contains($name, $search)
                    || str_contains($phone, $search)
                    || str_contains($status, $search);
            });
        }

        // =========================
        // TOTAL PAGO
        // =========================
        $totalPaid = $allTransactions->sum(function ($item) {

            return ($item['status'] ?? null) === 'paid'
                ? ($item['amount'] ?? 0)
                : 0;
        });

        // =========================
        // ORDENAÇÃO
        // =========================
        $allTransactions = $allTransactions
            ->sortByDesc('created_at')
            ->values();

        // =========================
        // PAGINAÇÃO MANUAL
        // =========================
        $totalItems = $allTransactions->count();

        $paginatedItems = $allTransactions
            ->forPage($currentPage, $perPage)
            ->values();

        // =========================
        // MAP RESPONSE
        // =========================
        $lista = $paginatedItems->map(function ($item) {

            return [
                'amount' => $item['amount'] ?? 0,
                'status' => $item['status'] ?? null,
                'name' => $item['user']['name'] ?? null,
                'payer_phone' => $item['payer_phone'] ?? null,
                'created_at' => $item['created_at'] ?? null,
            ];
        });

        // =========================
        // RESPONSE FINAL
        // =========================
        return response()->json([
            'success' => true,

            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => $status,
                'search' => $request->search,
            ],

            'total_paid' => $totalPaid,

            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => ceil($totalItems / $perPage),
            ],

            'data' => $lista
        ]);
    }



    ##apagar

 /*   public function reenviarCobrancasFalhas(): JsonResponse
    {
        $phones = [
            '553462438469',
            '5544998790120',
            '5547984573007',
            '5548999640382',
            '5549999888552',
            '5566992413888',
            '5566996847935',
            '5567981233280',
            '5568992282144',
            '5568992534214',
            '5568999932313',
            '5569981702417',
            '5581992758948',
            '5592994912189',
            '5596984088012',
            '5596991522004',
            '5598991785823',
        ];

        $enviadas = 0;
        $ignoradas = 0;
        $erros = 0;

        $clientes = Client::whereIn('phone', $phones)
            ->where('status', 'Ativo')
            ->with('user.settings')
            ->get();

        foreach ($clientes as $cliente) {
            try {
                $phoneOriginal = preg_replace('/\D/', '', $cliente->phone);

                $lid = $this->quepasa->chatIdConversa([
                    'phone' => $phoneOriginal,
                    'token' => $cliente->user->username,
                ]);

                if (!$lid || !str_contains($lid, '@lid')) {
                    $ignoradas++;
                    continue;
                }

                $mensagem = $cliente->user->settings->msg_padrao
                    ? str_replace(
                        ['[nome]', '[vencimento]', '[telefone]', '[tipo_cobranca]', '[valor]'],
                        [
                            $cliente->name,
                            Carbon::parse($cliente->vencimento)->format('d/m/Y'),
                            $cliente->phone,
                            $cliente->type_cobranca,
                            $cliente->value_mensalidade,
                        ],
                        $cliente->user->settings->msg_padrao
                    )
                    : $cliente->msg_enviar;

                $this->quepasa->sendTextService([
                    'message' => $mensagem,
                    'phone_cliente' => $lid,
                    'token' => $cliente->user->username,
                ]);

                $enviadas++;
                sleep(5);
            } catch (\Throwable $e) {
                $erros++;

                \Log::error('Erro ao reenviar cobrança falha', [
                    'phone' => $cliente->phone,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'total' => $clientes->count(),
            'enviadas' => $enviadas,
            'ignoradas' => $ignoradas,
            'erros' => $erros,
        ]);
    }*/
}
