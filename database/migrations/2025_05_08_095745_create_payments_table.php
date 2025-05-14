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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients');
            $table->date('data_criado');
            $table->enum('status', ['PENDENTE', 'PAGO'])->default('PENDENTE');
            $table->decimal('valor_debito', 8, 2);
            $table->enum('tipo_pagamento', ['BOLETO', 'PIX', 'CARTAO', 'TRANSFERENCIA'])->default('PIX');
            $table->enum('tipo_transacao', ['RECEITA', 'DESPESA'])->default('RECEITA');
            $table->date('data_pagamento')->nullable();
            $table->string('observation')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
