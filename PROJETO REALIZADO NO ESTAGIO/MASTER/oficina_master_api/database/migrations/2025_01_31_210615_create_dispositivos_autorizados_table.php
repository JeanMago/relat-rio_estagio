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
        Schema::create('dispositivos_autorizados', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_uuid');
            $table->uuid('tenant_uuid');
            $table->string('device_name'); // Nome do dispositivo
            $table->string('device_fingerprint')->unique(); // Identificação única do dispositivo
            $table->string('ip_address')->nullable(); // IP registrado
            $table->string('codigo_autorizacao')->nullable(); // codigo a ser confrontado pelo usuario
            $table->integer('tentativas')->default(1); // tentativas de colocar codigo
            $table->boolean('is_active')->default(true); // Ativo ou não
            $table->boolean('fulltime')->default(false); // Ativo ou não
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispositivos_autorizados');
    }
};
