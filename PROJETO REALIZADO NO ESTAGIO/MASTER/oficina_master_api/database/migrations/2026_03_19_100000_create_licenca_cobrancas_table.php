<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenca_cobrancas', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->unsignedBigInteger('licenca_id');
            $table->uuid('empresa_uuid');
            $table->uuid('tenant_uuid');
            $table->uuid('user_uuid')->nullable();
            $table->string('provider', 50)->default('mercado_pago');
            $table->string('status', 30)->default('pending');
            $table->string('external_reference', 120)->unique();
            $table->string('external_payment_id', 120)->nullable()->index();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('description')->nullable();
            $table->longText('qr_code')->nullable();
            $table->longText('qr_code_base64')->nullable();
            $table->text('ticket_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('last_status_payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_uuid', 'status']);
            $table->index(['empresa_uuid', 'status']);
            $table->index(['licenca_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenca_cobrancas');
    }
};
