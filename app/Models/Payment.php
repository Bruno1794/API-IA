<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    //
    protected $table = 'payments';
    protected $fillable = [
        'client_id',
        'data_criado',
        'status',
        'valor_debito',
        'tipo_pagamento',
        'tipo_transacao',
        'data_pagamento',
        'observation',
        'user_id'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
