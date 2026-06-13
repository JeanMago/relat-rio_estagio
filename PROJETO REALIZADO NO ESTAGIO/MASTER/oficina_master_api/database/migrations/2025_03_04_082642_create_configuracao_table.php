<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('configuracao', function (Blueprint $table) {
            $table->id(); // id como chave primária
            $table->string('provedor_email', 30)->nullable();
            $table->string('protocolo_email', 10)->nullable();
            $table->boolean('ssl_email')->default(true);
            $table->string('endereco_servidor_email', 250)->nullable();
            $table->integer('porta_email')->nullable();
            $table->string('usuario_email', 60)->nullable();
            $table->string('senha_email', 60)->nullable();
            $table->string('url_apiw')->nullable(); // string para URL da API
            $table->string('token')->nullable(); // string para o token de autenticação
            $table->string('token_impressor')->nullable();
            $table->string('key_apiw')->nullable();
            $table->string('bearer_token_apiw')->nullable();
            $table->text('menssagem_validacao')->nullable(); // texto longo para a mensagem OS
            $table->text('menssagem_vencimento')->nullable(); // texto longo para a mensagem OS
            $table->text('mensagem_financeiro')->nullable(); // texto longo para a mensagem financeiro
            $table->timestamps(); // campos created_at e updated_at automáticos
        });
    }

    public function down()
    {
        Schema::dropIfExists('empresas');
    }
};

