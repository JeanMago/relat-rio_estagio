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
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->integer('dias')->nullable();
            $table->integer('limit_user')->nullable();
            $table->integer('espaco_disco')->nullable();
            $table->integer('espaco_disco_adicional')->nullable();
            $table->decimal('valor_usuario_adicional', 8, 2)->nullable(); // Nome do banco de dados da empresa
            $table->decimal('valor_espaco_adicional', 8, 2)->nullable(); // Nome do banco de dados da empresa
            $table->decimal('valor', 8, 2)->nullable(); // Nome do banco de dados da empresa
            $table->decimal('valor_revenda', 8, 2)->nullable(); // Nome do banco de dados da empresa
            $table->text('descricao')->nullable(); // Usuário do banco de dados
            $table->string('modulos')->nullable(); // Senha do banco de dados
            $table->integer('status')->default('1');
            $table->date('licenca_valida_ate')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planos');
    }
};
