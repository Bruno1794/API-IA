<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    //
    protected $table = 'settings';
    protected $fillable = [
        'time_cobranca',
        'cadastro',
        'notificar',
        'envio',
        'msg_padrao',
        'user_id',
    ];
}
