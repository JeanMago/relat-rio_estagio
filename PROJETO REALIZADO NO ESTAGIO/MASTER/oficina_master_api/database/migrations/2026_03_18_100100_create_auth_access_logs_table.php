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
        Schema::create('auth_access_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('origem_app', 80)->default('oficina_api');
            $table->string('evento', 50)->default('login');
            $table->string('resultado', 30)->default('sucesso');
            $table->string('auth_guard', 50)->nullable();
            $table->uuid('user_uuid')->nullable();
            $table->uuid('tenant_uuid')->nullable();
            $table->uuid('empresa_uuid')->nullable();
            $table->uuid('master_user_uuid')->nullable();
            $table->string('email')->nullable();
            $table->string('nome')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser', 120)->nullable();
            $table->string('browser_version', 60)->nullable();
            $table->string('operating_system', 120)->nullable();
            $table->string('os_version', 60)->nullable();
            $table->string('device_type', 60)->nullable();
            $table->string('device_name', 120)->nullable();
            $table->boolean('is_mobile')->nullable();
            $table->boolean('is_desktop')->nullable();
            $table->boolean('is_bot')->nullable();
            $table->boolean('suspected_private_mode')->nullable();
            $table->string('session_id', 255)->nullable();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('request_id', 120)->nullable();
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['origem_app', 'evento', 'resultado']);
            $table->index(['user_uuid', 'tenant_uuid']);
            $table->index(['empresa_uuid', 'logged_in_at']);
            $table->index(['master_user_uuid', 'logged_in_at']);
            $table->index('session_id');
            $table->index('token_id');
            $table->index('request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_access_logs');
    }
};
