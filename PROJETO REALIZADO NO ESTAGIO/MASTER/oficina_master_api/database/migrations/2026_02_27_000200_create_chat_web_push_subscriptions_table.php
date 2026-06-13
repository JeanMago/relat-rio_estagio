<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('chat_web_push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_uuid');
            $table->unsignedBigInteger('user_id');
            $table->string('endpoint', 700)->unique();
            $table->string('p256dh', 255)->nullable();
            $table->string('auth_token', 255)->nullable();
            $table->string('content_encoding', 30)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->boolean('is_active')->default(true);

            $table->string('pending_title', 120)->nullable();
            $table->string('pending_body', 255)->nullable();
            $table->string('pending_url', 255)->nullable();
            $table->string('pending_tag', 120)->nullable();
            $table->json('pending_data')->nullable();

            $table->timestamp('last_push_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_uuid', 'user_id'], 'chat_webpush_tenant_user_idx');
            $table->index(['tenant_uuid', 'is_active'], 'chat_webpush_tenant_active_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('chat_web_push_subscriptions');
    }
};
