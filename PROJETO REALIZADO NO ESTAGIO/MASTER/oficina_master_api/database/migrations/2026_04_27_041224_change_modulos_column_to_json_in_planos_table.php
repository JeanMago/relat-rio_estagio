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
        Schema::table('planos', function (Blueprint $table) {
            $table->text('modulos')->nullable()->change();
        });

        Schema::table('licencas', function (Blueprint $table) {
            $table->text('modulos')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->string('modulos', 255)->nullable()->change();
        });

        Schema::table('licencas', function (Blueprint $table) {
            $table->string('modulos', 255)->nullable()->change();
        });
    }
};
