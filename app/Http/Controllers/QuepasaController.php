<?php

namespace App\Http\Controllers;


use App\Services\QuepasaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuepasaController extends Controller
{
    //
    protected $quepasa;

    public function __construct(QuepasaService $quepasa)
    {
        $this->quepasa = $quepasa;
    }

    public function gerarQr(): JsonResponse
    {
        $userLoagado = Auth::user();

        $response = $this->quepasa->gerarQrcodeService($userLoagado->username);

        return response()->json([
            'success' => true,
            'img' => $response
        ]);
    }

    public function statusConexao(): JsonResponse
    {
        $userLoagado = Auth::user();
        $response = $this->quepasa->statusService($userLoagado->username);
        $this->quepasa->webhookService($userLoagado->username);
        return response()->json([
            'success' => true,
            'status' => $response
        ], 200);
    }

    /*  public function sendText(Request $request): JsonResponse
      {
          $response = $this->quepasa->sendTextService($request->msg, $request->contact_number);

          return response()->json([
              'success' => true,
              'status' => $response
          ], 200);
      }*/
}
