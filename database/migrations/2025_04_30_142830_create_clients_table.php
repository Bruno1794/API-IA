<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('cpf')->nullable();
            $table->enum('status', ['Novo','Ativo', 'Inativo', 'Pendente', 'Cancelado'])->default('Ativo');
            $table->boolean('cobrar')->default(false); // se nao for cobrar marque como true
            $table->date('vencimento')->nullable();
            $table->integer('avisar')->nullable()->default(0);// avisar: no dia 0: 2,3,5 dias antes
            $table->decimal('value_mensalidade', 10, 2)->nullable();
            $table->text('msg_enviar')->nullable();
            $table->text('observation')->nullable();
            $table->enum('type_cobranca', ['MENSAL', 'BIMESTRAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL'])->default(
                'MENSAL'
            );
            $table->enum('preferencia', ['PIX', 'BOLETO', 'TRANSFERENCIA', 'CARTAO'])->default('PIX');
            $table->string('referencia')->nullable();
            $table->date('date_desativado')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
