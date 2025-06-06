<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarNotificacaoJob;
use App\Models\Client;
use App\Models\Notice;
use App\Models\Settings;
use app\Services\QuepasaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoticeController extends Controller
{
    //
    protected $quepasa;

    public function __construct(QuepasaService $quepasa)
    {
        $this->quepasa = $quepasa;
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

        $notificou = false;

        foreach ($clientes as $cliente) {
            $diasDesativado = Carbon::parse($cliente->date_desativado)->diffInDays(now());
            $user = $cliente->user;

            if (!$user->settings || !$user->settings->notificar) {
                continue;
            }

            foreach ($user->notices as $notice) {
                if ((int)$diasDesativado === (int)$notice->day) {
                    $mensagem = str_replace(
                        ['[nome]', '[vencimento]', '[telefone]', '[tipo_cobranca]', '[valor]'],
                        [
                            $cliente->name,
                            Carbon::parse($cliente->vencimento)->format('d/m/Y'),
                            $cliente->phone,
                            $cliente->type_cobranca,
                            $cliente->value_mensalidade,
                        ],
                        $notice->message
                    );

                    $this->quepasa->sendTextService([
                        'phone_cliente' => $cliente->phone,
                        'message' => $mensagem,
                        'token' => $user->username,
                    ]);

                    sleep(2); // Aguarda 2 segundos antes do próximo envio
                    $notificou = true;
                    break; // Envia apenas uma notificação por cliente
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => $notificou ? 'Notificações enviadas' : 'Sem notificações'
        ]);
    }


}
