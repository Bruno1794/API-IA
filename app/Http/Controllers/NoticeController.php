<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarNotificacaoJob;
use App\Models\Client;
use App\Models\Notice;
use App\Models\Settings;
use App\Services\KirogoService;
use app\Services\QuepasaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoticeController extends Controller
{
    //
    protected $quepasa;
    protected $kirago;

    public function __construct(QuepasaService $quepasa, KirogoService $kirogo)
    {
        $this->quepasa = $quepasa;
        $this->kirago = $kirogo;
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->input('pesquisa');

        $notices = Notice::where('user_id', Auth::id())
            ->orderBy('day', 'Asc')
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('day', 'LIKE', "%{$search}%")
                        ->orWhere('message', 'LIKE', "%{$search}%");
                });
            })
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $notices
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $userLogado = Auth::user();

        $noticia = Notice::create([
            'day' => $request->day,
            'message' => $request->message,
            'user_id' => $userLogado->id,
        ]);

        return response()->json([
            'success' => true,
            'notice' => $noticia
        ], 200);
    }

    public function show(Notice $notice): JsonResponse
    {
        $userLogado = Auth::user();

        // Verifica se o cliente pertence ao usuário logado
        if ($notice->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }
        return response()->json([
            'success' => true,
            'notice' => $notice
        ], 200);
    }

    public function update(Request $request, Notice $notice): JsonResponse
    {
        $userLogado = Auth::user();
        // Verifica se o cliente pertence ao usuário logado
        if ($notice->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }
        $notice->update([
            'day' => $request->day,
            'message' => $request->message,
        ]);
        return response()->json([
            'success' => true,
            'notice' => $notice
        ], 200);
    }

    public function destroy(Notice $notice): JsonResponse
    {
        $userLogado = Auth::user();
        // Verifica se o cliente pertence ao usuário logado
        if ($notice->user_id !== $userLogado->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não pertence ao usuário logado.',
            ], 403); // código 403 = proibido
        }
        $notice->delete();
        return response()->json([
            'success' => true,
            'notice' => $notice
        ], 200);
    }

    public function notifications(): JsonResponse
    {
        $clientes = Client::select(
            'id',
            'user_id',
            'name',
            'phone',
            'vencimento',
            'type_cobranca',
            'date_desativado',
            'value_mensalidade'
        )
            ->where('status', 'inativo')
            ->with([
                'user:id,phone,username',
                'user.notices:id,user_id,day,message',
                'user.settings:id,user_id,notificar'
            ])
            ->get();

        $enviadas = 0;
        $ignoradas = 0;
        $erros = 0;

        foreach ($clientes as $cliente) {

            // ==========================================
            // 1. VALIDA USUÁRIO
            // ==========================================

            $user = $cliente->user;

            if (!$user) {

                \Log::warning(
                    'Cliente inativo sem usuário vinculado',
                    [
                        'cliente_id' => $cliente->id,
                    ]
                );

                $ignoradas++;

                continue;
            }


            // ==========================================
            // 2. VALIDA CONFIGURAÇÕES
            // ==========================================

            if (
                !$user->settings
                || !$user->settings->notificar
            ) {
                $ignoradas++;
                continue;
            }


            // ==========================================
            // 3. VALIDA INSTÂNCIA KIRAGO
            // ==========================================

            if (!$user->username) {

                \Log::warning(
                    'Usuário sem instância Kirago configurada',
                    [
                        'cliente_id' => $cliente->id,
                        'user_id' => $user->id,
                    ]
                );

                $ignoradas++;

                continue;
            }


            // ==========================================
            // 4. VALIDA DATA DE DESATIVAÇÃO
            // ==========================================

            if (!$cliente->date_desativado) {

                \Log::warning(
                    'Cliente inativo sem data de desativação',
                    [
                        'cliente_id' => $cliente->id,
                    ]
                );

                $ignoradas++;

                continue;
            }


            $diasDesativado = Carbon::parse(
                $cliente->date_desativado
            )->diffInDays(now());


            // ==========================================
            // 5. PERCORRE AS NOTIFICAÇÕES
            // ==========================================

            foreach ($user->notices as $notice) {

                if (
                    (int) $diasDesativado
                    !==
                    (int) $notice->day
                ) {
                    continue;
                }


                // ==========================================
                // 6. MONTA MENSAGEM
                // ==========================================

                $valorFormatado = number_format(
                    (float) $cliente->value_mensalidade,
                    2,
                    ',',
                    '.'
                );


                $vencimentoFormatado = $cliente->vencimento
                    ? Carbon::parse(
                        $cliente->vencimento
                    )->format('d/m/Y')
                    : '';


                $mensagem = str_replace(
                    [
                        '[nome]',
                        '[vencimento]',
                        '[telefone]',
                        '[tipo_cobranca]',
                        '[valor]',
                    ],
                    [
                        $cliente->name,
                        $vencimentoFormatado,
                        $cliente->phone,
                        $cliente->type_cobranca,
                        $valorFormatado,
                    ],
                    $notice->message
                );


                // ==========================================
                // 7. VALIDA MENSAGEM
                // ==========================================

                if (!$mensagem) {

                    \Log::warning(
                        'Notificação sem mensagem configurada',
                        [
                            'cliente_id' => $cliente->id,
                            'notice_id' => $notice->id,
                        ]
                    );

                    $ignoradas++;

                    break;
                }


                // ==========================================
                // 8. NORMALIZA TELEFONE
                // ==========================================

                $phoneDestino = preg_replace(
                    '/\D/',
                    '',
                    (string) $cliente->phone
                );


                if (!str_starts_with(
                    $phoneDestino,
                    '55'
                )) {
                    $phoneDestino =
                        '55' . $phoneDestino;
                }


                // ==========================================
                // 9. DADOS PARA O KIRAGO
                // ==========================================

                $dados = [
                    'phone_cliente' =>
                        $phoneDestino,

                    'message' =>
                        $mensagem,

                    'token' =>
                        $user->username,
                ];


                // ==========================================
                // 10. ENVIA PELO KIRAGO
                // ==========================================

                try {

                    $retorno =
                        $this->kirago
                            ->enviarMensagem(
                                $dados
                            );


                    // ==========================================
                    // 11. VERIFICA RETORNO
                    // ==========================================

                    if (
                        !isset($retorno['success'])
                        || $retorno['success'] !== true
                    ) {

                        \Log::error(
                            'Erro no envio da notificação pelo Kirago',
                            [
                                'cliente_id' =>
                                    $cliente->id,

                                'phone' =>
                                    $phoneDestino,

                                'instancia' =>
                                    $user->username,

                                'notice_id' =>
                                    $notice->id,

                                'retorno' =>
                                    $retorno,
                            ]
                        );

                        $erros++;

                        break;
                    }


                    // ==========================================
                    // 12. SUCESSO
                    // ==========================================

                    $enviadas++;


                    \Log::info(
                        'Notificação enviada pelo Kirago',
                        [
                            'cliente_id' =>
                                $cliente->id,

                            'phone' =>
                                $phoneDestino,

                            'instancia' =>
                                $user->username,

                            'notice_id' =>
                                $notice->id,

                            'dias_desativado' =>
                                $diasDesativado,
                        ]
                    );


                    /*
                     * Aguarda antes do próximo envio.
                     */
                    sleep(2);

                } catch (\Throwable $e) {

                    \Log::error(
                        'Erro ao processar notificação',
                        [
                            'cliente_id' =>
                                $cliente->id,

                            'phone' =>
                                $phoneDestino,

                            'instancia' =>
                                $user->username,

                            'erro' =>
                                $e->getMessage(),

                            'arquivo' =>
                                $e->getFile(),

                            'linha' =>
                                $e->getLine(),
                        ]
                    );

                    $erros++;
                }


                /*
                 * Uma única notificação por cliente
                 * nessa execução.
                 */
                break;
            }
        }


        // ==========================================
        // 13. RETORNO
        // ==========================================

        return response()->json([
            'success' => true,

            'message' =>
                $enviadas > 0
                    ? 'Notificações processadas.'
                    : 'Sem notificações para enviar.',

            'total_clientes' =>
                $clientes->count(),

            'enviadas' =>
                $enviadas,

            'ignoradas' =>
                $ignoradas,

            'erros' =>
                $erros,
        ], 200);
    }

}
