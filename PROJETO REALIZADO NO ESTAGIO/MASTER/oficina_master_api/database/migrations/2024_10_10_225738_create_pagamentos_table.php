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
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->uuid('empresa_uuid'); // Cliente que realizou o pagamento
            $table->decimal('valor_pago', 10, 2); // Valor do pagamento
            $table->datetime('data_pagamento'); // Data em que o pagamento foi realizado
            $table->string('forma_pagamento')->nullable(); // Forma de pagamento: 'cartão', 'dinheiro', 'boleto', etc.
            $table->Integer('referencia_transacao')->nullable(); // ID da transação correspondente na tabela 'transacoes'
            $table->uuid('usuario_uuid')->nullable(); // ID ddo usuario que recebeu
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
