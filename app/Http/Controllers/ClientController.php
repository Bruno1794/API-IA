<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarMensagemWhatsApp;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use App\Services\QuepasaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    //
    protected $quepasa;

    public function __construct(QuepasaService $quepasa)
    {
        $this->quepasa = $quepasa;
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

        // Contagem de clientes novos (criados no intervalo especificado)
        $clientesNovos = Client::where('user_id', Auth::id())
            ->where('status', 'Ativo')
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();

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
            "date_desativado" => $request->status === "Inativo" || $request->status === "Cancelado" ? carbon::now(
            ) : null,
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

            $dados = [
                'message' => $cliente->msg_enviar ?? 'Mensagem padrão de cobrança',
                'phone_cliente' => $cliente->phone,
                'token' => $cliente->user->username,
            ];

            try {
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
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erro ao enviar mensagem'], 500);
            } finally {
                // Garante que o processamento será finalizado, mesmo em caso de erro
                $cliente->update(['is_processing' => false]);
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


}
