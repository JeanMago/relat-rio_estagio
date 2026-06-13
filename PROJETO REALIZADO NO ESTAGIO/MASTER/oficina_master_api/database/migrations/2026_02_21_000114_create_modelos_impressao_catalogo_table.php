<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('master')->create('modelos_impressao_catalogo', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('nome', 160);
            $table->string('contexto', 120)->index();
            $table->string('engine', 60)->nullable();
            $table->string('formato_documento', 60)->nullable();
            $table->string('impressora_tipo_default', 60)->nullable();
            $table->text('descricao')->nullable();
            $table->string('imagem_exemplo_url')->nullable();
            $table->json('payload_exemplo')->nullable();
            $table->json('campos_configuraveis')->nullable();
            $table->boolean('layout_bloqueado')->default(false)->index();
            $table->boolean('sistema')->default(true)->index();
            $table->boolean('ativo')->default(true)->index();
            $table->unsignedInteger('ordem')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('modelos_impressao_catalogo');
    }
};

