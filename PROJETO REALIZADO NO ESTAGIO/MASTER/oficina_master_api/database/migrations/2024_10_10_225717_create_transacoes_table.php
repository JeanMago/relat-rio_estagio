<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('transacoes', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('tipo'); // Tipo de transação: 'venda', 'chamado', 'pagamento'
            $table->Integer('referencia_id'); // ID da referência da transação (venda, chamado ou pagamento)
            $table->string('descricao')->nullable(); // Descrição da transação
            $table->decimal('valor', 10, 2)->default(0.00); // Valor da transação
            $table->decimal('desconto', 6, 2)->default(0.00); // Valor da transação
            $table->decimal('juros', 6, 2)->default(0.00); // Valor da transação
            $table->decimal('multa', 6, 2)->default(0.00); // Valor da transação
            $table->decimal('outros', 6, 2)->default(0.00); // Valor da transação
            $table->decimal('valorpago', 6, 2)->default(0.00); // Valor da transação
            $table->decimal('troco', 6, 2)->default(0.00); // Valor da transação
            $table->date('data'); // Data da transação
            $table->integer('status')->default('1'); // Status da transação: 'pendente', 'pago', 'cancelado'
            $table->uuid('empresa_uuid')->nullable(); // Cliente relacionado à transação
            $table->uuid('user_uuid')->nullable(); // Usuário que registrou a transação
            $table->string('forma_pagamento')->nullable(); // Descreve a forma de pagamento
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transacoes');
    }
};
