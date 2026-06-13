<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('master')->create('ibpt_itens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ibpt_versao_id');
            $table->string('uf', 2)->nullable();
            $table->string('ncm', 8);
            $table->string('ex_tipi', 3)->nullable();
            $table->string('descricao', 255)->nullable();
            $table->decimal('aliquota_federal_nacional', 8, 4)->default(0);
            $table->decimal('aliquota_federal_importado', 8, 4)->default(0);
            $table->decimal('aliquota_estadual', 8, 4)->default(0);
            $table->decimal('aliquota_municipal', 8, 4)->default(0);
            $table->string('chave', 80)->nullable();
            $table->string('fonte', 60)->default('IBPT');
            $table->timestamps();

            $table->index(['ibpt_versao_id', 'ncm', 'uf']);
            $table->unique(['ibpt_versao_id', 'ncm', 'ex_tipi', 'uf'], 'uq_ibpt_item_versao_ncm_ex_uf');
            $table->foreign('ibpt_versao_id')->references('id')->on('ibpt_versoes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('ibpt_itens');
    }
};

