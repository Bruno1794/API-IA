<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

##Rotas Publicas
##login
Route::post('login', [\App\Http\Controllers\LoginController::class, 'login'])->name('login');

##Cadastrar LED
Route::post('usuarios-lead', [\App\Http\Controllers\UserController::class, 'storeLed']);


## Clientes
Route::post('clientes', [\App\Http\Controllers\ClientController::class, 'storeWhats']);
Route::get('cobranca', [\App\Http\Controllers\ClientController::class, 'cobranca']);


Route::get('respostas', [\App\Http\Controllers\AgentController::class, 'index']);
Route::post('seed', [\App\Http\Controllers\QuepasaController::class, 'sendText']);

Route::get('enviar-noticacoes', [\App\Http\Controllers\NoticeController::class, 'notifications']);


##Fim

##Rotas Privadas
Route::group(['middleware' => ['auth:sanctum']], function () {

    #User
    Route::get('usuarios', [\App\Http\Controllers\UserController::class, 'show']);

    ## Settings
    Route::get('settings', [\App\Http\Controllers\SettingController::class, 'index']);
    Route::post('settings', [\App\Http\Controllers\SettingController::class, 'store']);

    ## QUEPASA
    Route::post('qrcode', [\App\Http\Controllers\QuepasaController::class, 'gerarQr']);
    Route::get('status', [\App\Http\Controllers\QuepasaController::class, 'statusConexao']);

    ## Clientes
    Route::get('clientes', [\App\Http\Controllers\ClientController::class, 'index']);
    Route::get('contagem', [\App\Http\Controllers\ClientController::class, 'listCont']);
    Route::get('lista-novos-clientes', [\App\Http\Controllers\ClientController::class, 'listClientNew']);
    Route::post('novo-cliente', [\App\Http\Controllers\ClientController::class, 'store']);
    Route::post('renovar/{client}', [\App\Http\Controllers\ClientController::class, 'Renew']);
    Route::put('clientes/{client}', [\App\Http\Controllers\ClientController::class, 'update']);
    Route::get('clientes/{client}', [\App\Http\Controllers\ClientController::class, 'show']);
    Route::put('update-status/{client}', [\App\Http\Controllers\ClientController::class, 'UpdateStatus']);
    Route::put('update-tipo-cliente/{client}', [\App\Http\Controllers\ClientController::class, 'UpdateTypeClient']);
    Route::put('ativar-cleinte/{client}', [\App\Http\Controllers\ClientController::class, 'AtivaClient']);
    Route::delete('cleintes-delete/{client}', [\App\Http\Controllers\ClientController::class, 'destroy']);
    Route::get('payments/{client}', [\App\Http\Controllers\ClientController::class, 'payments']);

    ## Pagamentos
    Route::get('pagamentos', [\App\Http\Controllers\PaymentsController::class, 'index']);
    Route::get('pagamentos-dados', [\App\Http\Controllers\PaymentsController::class, 'filtroPagamentos']);
    Route::put('pagamentos/{payment}', [\App\Http\Controllers\PaymentsController::class, 'update']);
    Route::delete('pagamentos/{payment}', [\App\Http\Controllers\PaymentsController::class, 'destroy']);

    ## Noticação
    Route::get('noficacoes', [\App\Http\Controllers\NoticeController::class, 'index']);
    Route::post('noficacoes', [\App\Http\Controllers\NoticeController::class, 'store']);
    Route::get('noficacoes/{notice}', [\App\Http\Controllers\NoticeController::class, 'show']);
    Route::put('noficacoes/{notice}', [\App\Http\Controllers\NoticeController::class, 'update']);
    Route::delete('noficacoes/{notice}', [\App\Http\Controllers\NoticeController::class, 'destroy']);
});
##Fim
