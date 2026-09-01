<?php

namespace App\Http\Controllers;


use App\Services\KirogoService;
use App\Services\QuepasaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KiragoController extends Controller
{
    //
    protected $kirago;

    public function __construct(KirogoService $kirago)
    {
        $this->kirago = $kirago;
    }


    public function criarSessao(): JsonResponse
    {
        $userLoagado = Auth::user();
        $response = $this->kirago->criarSessaoKira($userLoagado->username);

        return response()->json([
            'success' => true,
            "data" => $response
        ], 200);

    }




}
