<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenca_checkout_tokens', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('token_hash', 64)->unique();
            $table->uuid('tenant_uuid');
            $table->uuid('empresa_uuid');
            $table->uuid('user_uuid')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_uuid', 'status']);
            $table->index(['empresa_uuid', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenca_checkout_tokens');
    }
};
