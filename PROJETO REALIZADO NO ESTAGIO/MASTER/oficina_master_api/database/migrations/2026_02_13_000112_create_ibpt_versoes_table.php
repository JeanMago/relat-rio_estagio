<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('master')->create('ibpt_versoes', function (Blueprint $table) {
            $table->id();
            $table->string('versao', 30)->unique();
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->string('fonte', 60)->default('IBPT');
            $table->string('arquivo_path', 255)->nullable();
            $table->string('hash_arquivo', 80)->nullable();
            $table->boolean('ativa')->default(false);
            $table->timestamp('publicada_em')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['ativa', 'publicada_em']);
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('ibpt_versoes');
    }
};

