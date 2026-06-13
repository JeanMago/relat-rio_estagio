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
        Schema::create('impressaos', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_uuid')->nullable();
            $table->string('impressora', 60)->nullable();
            $table->text('documento')->nullable();
            $table->integer('quantidade')->nullable();
            $table->boolean('printed')->default(false);
            $table->string('tipo', 30)->nullable();
            $table->string('token_app', 30)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impressaos');
    }
};
