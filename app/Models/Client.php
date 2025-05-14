<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    //
    protected $table = 'clients';
    protected $fillable = [
        'name',
        'phone',
        'email',
        'cpf',
        'status',
        'cobrar',
        'cobranca',
        'vencimento',
        'value_mensalidade',
        'msg_enviar',
        'avisar',
        'date_desativado',
        'type_cobranca',
        'referencia',
        'observation',
        'preferencia',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function settings()
    {
        return $this->hasOne(Settings::class); // Ajuste para o relacionamento correto
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }


}
