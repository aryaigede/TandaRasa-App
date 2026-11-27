<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persetujuan registrasi (FR-03) dan preferensi pengingat (FR-05, FR-22).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('consent_version', 20)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->boolean('reminder_enabled')->default(false);
            $table->time('reminder_time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['consent_version', 'consented_at', 'reminder_enabled', 'reminder_time']);
        });
    }
};
