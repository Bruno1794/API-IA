<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->time('time_cobranca');
            $table->boolean('cadastro')->default(false);//cadastro automatico se false nao cadastra
            $table->boolean('notificar')->default(false);//msg de noticação se false nao notificar
            $table->boolean('envio')->default(false);//enviar faturas automatica se false nao enviar
            $table->string('msg_padrao')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
