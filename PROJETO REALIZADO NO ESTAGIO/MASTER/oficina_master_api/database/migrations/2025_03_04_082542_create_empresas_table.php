<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->unsignedSmallInteger('status')->default(1)->comment('Identifica o status do registro. 1=ativo, 2=bloqueado, 3=inativo');
            $table->string('nome', 250)->comment('Nome do Cliente/Fornecedor/Emitente etc (Pessoas em geral)');
            $table->string('apelido', 100)->nullable()->comment('Apelido ou nome fantasia');
            $table->string('cpf_cnpj', 14)->nullable()->comment('CPF ou CNPJ do parceiro');
            $table->date('nascimento')->nullable()->comment('Data de Nascimento ou abertura.');
            $table->string('site', 150)->nullable()->comment('Site da pessoa');
            $table->string('rg', 15)->nullable()->comment('RG - Registro geral de nascimento');
            $table->unsignedSmallInteger('regime_tributario')->nullable()->comment('Código do regime tributário, 1 = Simples Nacional; 2 = Simples Nacional, excesso sublimite de receita bruta; 3 = Regime Normal.');
            $table->unsignedSmallInteger('contribuinte')->nullable()->comment('Identifica se o cliente é contribuinte. Conteúdo: 1=Contribuinte ICMS (informar a IE do destinatário); 2=Contribuinte isento de Inscrição no cadastro de Contribuintes do ICMS; 9=Não Contribuinte, que pode ou não possuir Inscrição Estadual no Cadastro de Contribuintes do ICMS.');
            $table->string('ie', 15)->nullable()->comment('Inscrição estadual');
            $table->string('im', 15)->nullable()->comment('Inscrição municipal');
            $table->string('ir', 15)->nullable()->comment('Inscrição rural');
            $table->string('suframa', 15)->nullable()->comment('Inscrição do Suframa');
            $table->string('cnae', 7)->nullable()->comment('Classificação Nacional de Atividades Econômicas.');
            $table->string('sub_st', 15)->nullable()->comment('Inscrição substituto tributário');
            $table->string('email', 60)->nullable();
            $table->string('telefone', 15)->nullable();
            $table->string('cep', 15)->nullable();
            $table->string('logradouro', 30)->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('cod_uf', 2)->nullable();
            $table->string('municipio', 30)->nullable();
            $table->string('numero', 5)->nullable();
            $table->string('complemento', 30)->nullable();
            $table->string('bairro', 30)->nullable();
            $table->string('cod_municipio', 15)->nullable();
            $table->integer('agenciador_id')->nullable();
            $table->boolean('emite_nfce')->nullable();
            $table->boolean('emite_nfse')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable()->comment('Identificação do usuário que criou o registro');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Identificação do usuário que alterou o registro');
            $table->unsignedBigInteger('deleted_by')->nullable()->comment('Identificação do usuário que excluiu o registro');

        });
    }

    public function down()
    {
        Schema::dropIfExists('empresas');
    }
};

