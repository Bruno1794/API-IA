<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarMensagemWhatsApp;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use App\Services\fastDepixService;
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
    protected $fastDepix;

    public function __construct(QuepasaService $quepasa, FastDepixService $fastDepix)
    {
        $this->quepasa = $quepasa;
        $this->fastDepix = $fastDepix;
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

    public function storeWhats(Request $request): JsonResponse
    {
        $dadosAdmin = User::with('settings')
            ->where('phone', Str::before($request->phone, ':'))->first();

        if (!$dadosAdmin) {
            return response()->json(['error' => 'Usuário administrador não encontrado.'], 404);
        }

        // Normalizando o número do cliente (removendo caracteres não numéricos)
        $phone = preg_replace('/\D/', '', Str::before($request->phone_cliente, '@'));


        // Garantir que o número tenha o prefixo 55
        if (!str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        }

        // Verificar se o número possui 13 dígitos (55 + DDD + 9 + número)
        if (strlen($phone) === 12) {
            // Adiciona o dígito 9 após o DDD
            $phone = substr($phone, 0, 4) . '9' . substr($phone, 4);
        }

        // Validação final do número (com o prefixo 55)
        if (!preg_match('/^55\d{2}9\d{4}\d{4}$/', $phone)) {
            return response()->json(['error' => 'Número de telefone do cliente não é válido.'], 422);
        }

        if ($request->type === 'text' && $dadosAdmin->settings->cadastro) {

            $cliente = Client::firstOrCreate(
                ['phone' => $phone],
                [
                    'user_id' => $dadosAdmin->id,
                    'name' => $request->name,
                    'status' => "Novo",
                ]
            );
        }

        if ($cliente->status !== "Novo" && !empty($request->renovar)) {

            // 2. O Regex já valida o formato (# + número) e extrai o número de forma segura
            if (preg_match('/^#(\d+)$/', $request->renovar, $matches)) {
                $numero = $matches[1];

                $dados = [
                    'message' => "Para renovar seu serviço é bem simples:\n\n1️⃣ Clique no link abaixo\n2️⃣ Escaneie o QR Code ou copie a chave PIX exibida na página\n3️⃣ Após o pagamento, a confirmação acontece automaticamente ✅\n\n🔗 https://servico.ddns.net/{$cliente->phone}/{$numero}",
                    'phone_cliente' => $cliente->phone,
                    'token' => $cliente->user->username,
                ];

                $this->quepasa->sendTextService($dados);

            } else {
                // Retorno amigável para o usuário ou log em vez de travar o app com dd() em produção
                return response()->json(['error' => "Formato inválido: {$request->renovar}"], 422);
            }
        }


        return response()->json([
            'success' => true,
            'client' => $cliente
        ], 200);
    }
//    public function cobranca(): JsonResponse
//    {
//        $horaAtual = Carbon::now()->format('H:i');
//        $clientes = Client::where('status', 'Ativo')
//            ->where('cobrar', false)
//            ->with('user.settings')
//            ->get()
//            ->filter(function ($cliente) {
//                // Verifica se a data atual + $cliente->avisar dias é igual ao vencimento
//                return Carbon::parse($cliente->vencimento)
//                    ->isSameDay(Carbon::today()->addDays($cliente->avisar ?? 0));
//            });
//
//        //enviar msg de cobrança para whatsapp
//        foreach ($clientes as $index => $cliente) {
//            if ($cliente->user->settings->time_cobranca < $horaAtual) {
//                dispatch(new EnviarMensagemWhatsApp($cliente->id))->delay(
//                    now()->addSeconds($index * 10)
//                ); // envia um a cada 10s
//            }
//        }
//        //fim
//
//        return response()->json([
//            'success' => true,
//            'clients' => $clientes
//        ]);
//    }

    /*  public function cobranca(): JsonResponse
      {
          $horaAtual = Carbon::now()->format('H:i');
          $clientes = Client::where('status', 'Ativo')
              ->where('cobrar', false)
              ->with('user.settings')
              ->get()
              ->filter(function ($cliente) {
                  // Verifica se a data atual + $cliente->avisar dias é igual ao vencimento
                  return Carbon::parse($cliente->vencimento)
                      ->isSameDay(Carbon::today()->addDays($cliente->avisar ?? 0));
              });


          foreach ($clientes as $cliente) {
              $vencimentoAtual = Carbon::parse($cliente->vencimento);
              $novoVencimento = $vencimentoAtual; // Inicializa com o vencimento atual

              if ($cliente->user->settings->time_cobranca < $horaAtual) {

                  switch ($cliente->type_cobranca) {
                      case 'MENSAL':
                          $novoVencimento = $vencimentoAtual->addMonth();
                          break;

                      case 'BIMESTRAL':
                          $novoVencimento = $vencimentoAtual->addMonths(2);
                          break;

                      case 'TRIMESTRAL':
                          $novoVencimento = $vencimentoAtual->addMonths(3);
                          break;

                      case 'SEMESTRAL':
                          $novoVencimento = $vencimentoAtual->addMonths(6);
                          break;

                      case 'ANUAL':
                          $novoVencimento = $vencimentoAtual->addYear();
                          break;

                      default:
                          // Opcional: lançar uma exceção ou logar o erro
                          break;
                  }

                  $dados = [
                      'message' => $cliente->msg_enviar ?? 'Mensagem padrão de cobrança',
                      'phone_cliente' => $cliente->phone,
                      'token' => $cliente->user->username,
                  ];

                  try {
                      $this->quepasa->sendTextService($dados);
                  } catch (\Exception $e) {
                      // Opcional: logar erro de envio
                      return response()->json(['error' => 'Erro ao enviar mensagem'], 500);
                  }

                  $cliente->update([
                      'vencimento' => $novoVencimento,

                  ]);

                  $cliente->payments()->create([
                      'user_id' => $cliente->user_id,
                      'data_criado' => Carbon::today()->toDateString(),
                      'valor_debito' => $cliente->value_mensalidade,
                      'tipo_pagamento' => $cliente->preferencia,
                  ]);
              }



              // Pausa de 2 segundos entre cada envio
              sleep(5);
          }

          return response()->json(['success' => true], 200);
      }*/

    public function cobranca(): JsonResponse
    {
        $horaAtual = Carbon::now()->format('H:i');

        // Filtrar apenas clientes cujo horário de cobrança já passou
        $clientes = Client::where('status', 'Ativo')
            ->where('cobrar', false)
            ->where('is_processing', false)  // Apenas clientes que não estão em processamento
            ->with('user.settings')
            ->get()
            ->filter(function ($cliente) use ($horaAtual) {
                // Verifica se o horário de cobrança já passou
                if ($cliente->user->settings->time_cobranca >= $horaAtual) {
                    return false;
                }

                // Verifica se a data atual + $cliente->avisar dias é igual ao vencimento
                return Carbon::parse($cliente->vencimento)
                    ->isSameDay(Carbon::today()->addDays($cliente->avisar ?? 0));
            });


        // Se não houver clientes que atendem aos critérios, retornar sucesso
        if ($clientes->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Nenhuma cobrança a ser realizada no momento.'],
                200);
        }

        foreach ($clientes as $cliente) {
            // Tenta marcar o cliente como em processamento de forma segura (retorna 1 se atualizado)
            $atualizado = Client::where('id', $cliente->id)
                ->where('is_processing', false)
                ->update(['is_processing' => true]);

            // Se não conseguiu atualizar, significa que já está sendo processado
            if ($atualizado === 0) {
                continue;
            }

            $vencimentoAtual = Carbon::parse($cliente->vencimento);
            $novoVencimento = $vencimentoAtual;

            // Atualiza o vencimento conforme o tipo de cobrança
            switch ($cliente->type_cobranca) {
                case 'MENSAL':
                    $novoVencimento = $vencimentoAtual->addMonth();
                    break;
                case 'BIMESTRAL':
                    $novoVencimento = $vencimentoAtual->addMonths(2);
                    break;
                case 'TRIMESTRAL':
                    $novoVencimento = $vencimentoAtual->addMonths(3);
                    break;
                case 'SEMESTRAL':
                    $novoVencimento = $vencimentoAtual->addMonths(6);
                    break;
                case 'ANUAL':
                    $novoVencimento = $vencimentoAtual->addYear();
                    break;
            }

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
                $cliente->user->settings->msg_padrao
            );


            $dados = [
                'message' => $cliente->user->settings->msg_padrao ? $mensagem : $cliente->msg_enviar,
                'phone_cliente' => $cliente->phone,
                'token' => $cliente->user->username,
            ];

            try {
                $status = $this->quepasa->statusService($cliente->user->username);
                if ($status === "Ready") {
                    $this->quepasa->sendTextService($dados);
                    // Atualiza o vencimento e cria o pagamento
                    $cliente->update([
                        'vencimento' => $novoVencimento,
                    ]);

                    $cliente->payments()->create([
                        'user_id' => $cliente->user_id,
                        'data_criado' => Carbon::today()->toDateString(),
                        'valor_debito' => $cliente->value_mensalidade,
                        'tipo_pagamento' => $cliente->preferencia,
                    ]);

                    // Pausa de 5 segundos entre cada envio
                    sleep(5);
                }
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erro ao enviar mensagem'], 500);
            } finally {
                // Garante que o processamento será finalizado, mesmo em caso de erro
                //$cliente->update(['is_processing' => false]);

                $cliente->is_processing = false;
                $cliente->save();
            }
        }

        return response()->json(['success' => true], 200);
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

    public function fastDepix(Request $request): JsonResponse
    {
        $phone = $request->phone;
        $valor = $request->value_cobranca;

        $cacheKey = "fastdepix_pix_{$phone}_{$valor}";

        $pixExistente = Cache::get($cacheKey);

        if ($pixExistente) {
            $depixId = data_get($pixExistente, 'dadosDepix.data.depix_transaction_id');

            $statusCache = Cache::get("fastdepix_status_{$depixId}");

            $status = data_get($statusCache, 'status')
                ?? data_get($pixExistente, 'dadosDepix.data.status');

            if (!in_array($status, ['paid', 'expired', 'transaction.paid', 'transaction.expired'])) {
                return response()->json([
                    'success' => true,
                    'reused' => true,
                    'dadosDepix' => data_get($pixExistente, 'dadosDepix'),
                ]);
            }
        }

        $cliente = Client::where('phone', $phone)->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não encontrado'
            ], 404);
        }

        $dados = [
            'amount' => $valor,
            'user' => [
                'name' => $cliente->referencia ?: $cliente->name,
                'user_type' => 'individual',
            ],
            'payer_phone' => $cliente->phone,
            'notification_url' => 'https://api.codeacode.com.br/api/webhooks/fastdepix',
        ];

        $dadosDepix = $this->fastDepix->gerarTransction($dados);

        Cache::put($cacheKey, [
            'dadosDepix' => $dadosDepix
        ], now()->addMinutes(20));

        return response()->json([
            'success' => true,
            'reused' => false,
            'dadosDepix' => $dadosDepix,
        ]);
    }

    public function webhooks(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('FASTDEPIX WEBHOOK RECEBIDO', $payload);

        $transactionId =
            data_get($payload, 'depix_transaction_id') ??
            data_get($payload, 'data.depix_transaction_id') ??
            data_get($payload, 'transaction_id') ??
            data_get($payload, 'data.transaction_id') ??
            data_get($payload, 'id') ??
            data_get($payload, 'data.id');

        $status =
            data_get($payload, 'status') ??
            data_get($payload, 'data.status') ??
            data_get($payload, 'event');

        $event = data_get($payload, 'event');

        if (!$transactionId) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction ID não encontrado',
                'payload' => $payload,
            ], 400);
        }

        Cache::put("fastdepix_status_{$transactionId}", [
            'status' => $status,
            'event' => $event,
            'transaction_id' => $transactionId,
            'payload' => $payload,
            'updated_at' => now()->toDateTimeString(),
        ], now()->addHours(2));

        return response()->json([
            'success' => true,
            'message' => 'Webhook recebido',
            'status' => $status,
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

        $currentPage = (int) ($request->page ?? 1);

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
}
