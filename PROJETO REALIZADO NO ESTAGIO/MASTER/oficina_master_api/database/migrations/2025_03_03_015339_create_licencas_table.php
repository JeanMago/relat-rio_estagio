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
        Schema::create('licencas', function (Blueprint $table) {
            $table->id();
            $table->uuid('empresa_uuid')->nullable(); // Cadastro da empresa
            $table->uuid('tenant_uuid')->nullable(); // Cadastro da empresa
            $table->integer('plano_id')->nullable(); // Cadastro da empresa
            $table->uuid('vendedor_uuid')->nullable(); // Cadastro da empresa
            $table->boolean('tipo_contratacao')->default(1);
            $table->boolean('tipo_experiencia')->default(0);
            $table->boolean('tipo_demosntracao')->default(0);
            $table->boolean('renovacao_automatica')->default(0);
            $table->boolean('ocultar_mensagem_vencimento')->default(0);
            $table->string('forma_pagamento_renovacao')->nullable(); // Usuário do banco de dados
            $table->boolean('bloqueada')->default(0);
            $table->date('data_inicio')->nullable();
            $table->date('data_expiracao')->nullable();
            $table->integer('usuario_adicionais')->nullable();
            $table->integer('limit_user')->nullable();
            $table->integer('espaco_disco')->nullable();
            $table->integer('espaco_disco_adicional')->nullable();
            $table->integer('empresas_disponiveis')->default(0);
            $table->decimal('valor_empresa_disponivel', 8, 2)->nullable(); // Nome do banco de dados da empresa
            $table->decimal('valor_usuario_adicional', 8, 2)->nullable(); // Nome do banco de dados da empresa
            $table->decimal('valor_espaco_adicional', 8, 2)->nullable(); // Nome do banco de dados da empresa
            $table->decimal('valor', 8, 2)->nullable(); // Nome do banco de dados da empresa
            $table->decimal('valor_revenda', 8, 2)->nullable(); // Nome do banco de dados da empresa
            $table->string('forma_pagamento')->nullable(); // Usuário do banco de dados
            $table->string('modulos')->nullable(); // Senha do banco de dados
            $table->integer('empresa_para_nfse')->nullable(); // Senha do banco de dados
            $table->text('observacoes')->nullable(); // Senha do banco de dados
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licencas');
    }
};
