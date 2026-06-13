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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('uuid')->primary(); // UUID como chave primária
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->string('remember_token', 100)->nullable();
            $table->Integer('permission_id')->nullable();
            $table->string('telefone', 16)->nullable();
            $table->string('foto', 120)->nullable();
            $table->Integer('por_page')->nullable();
            $table->string('senha_rapida', 10)->nullable();
            $table->Integer('pessoa_id')->nullable();
            $table->Integer('depositoatual')->nullable();
            $table->string('cpf', 15)->nullable();
            $table->string('rg', 15)->nullable();
            $table->string('cep', 15)->nullable();
            $table->string('endereco', 50)->nullable();
            $table->string('bairro', 30)->nullable();
            $table->string('complemento', 30)->nullable();
            $table->string('cidade', 25)->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('dashboard', 40)->nullable();
            $table->boolean('is_tecnico')->default(false);
            $table->boolean('status')->default(true);
            $table->uuid('tenant_uuid')->nullable()->constrained('tenants')->onDelete('cascade');
            $table->enum('role', ['master', 'agenciador', 'admin', 'user'])->default('user');
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable()->comment('Identificação do usuário que criou o registro');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Identificação do usuário que alterou o registro');
            $table->unsignedBigInteger('deleted_by')->nullable()->comment('Identificação do usuário que excluiu o registro');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
