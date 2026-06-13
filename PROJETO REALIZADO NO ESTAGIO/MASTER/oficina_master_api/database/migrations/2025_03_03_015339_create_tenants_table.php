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
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('name');
            $table->string('nome_suporte')->nullable();  //Nome quem vai dar suporte
            $table->string('telefone_suporte')->nullable();  //telefone do suporte
            $table->integer('plano')->nullable();
            $table->uuid('agenciador_uuid')->nullable(); // Id do agenciador
            $table->uuid('empresa_uuid')->nullable(); // Cadastro da empresa
            $table->string('url_banco')->default('127.0.0.1'); // URL do banco de dados da empresa
            $table->integer('porta')->default('3306'); // Porta do banco de dados da empresa
            $table->string('database'); // Nome do banco de dados da empresa
            $table->string('username'); // Usuário do banco de dados
            $table->string('password'); // Senha do banco de dados
            $table->enum('status', ['ativo', 'bloqueado', 'desativado'])->default('ativo');
            $table->date('licenca_valida_ate')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable()->comment('Identificação do usuário que criou o registro');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Identificação do usuário que alterou o registro');
            $table->unsignedBigInteger('deleted_by')->nullable()->comment('Identificação do usuário que excluiu o registro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
