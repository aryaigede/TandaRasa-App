<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kontak Pusat Dukungan (FR-18, FR-19, FR-24). Hanya dari sumber resmi; jangan dikarang.
     */
    public function up(): void
    {
        Schema::create('support_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('hours')->nullable();
            $table->string('url')->nullable();
            $table->string('source');
            $table->date('verified_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_contacts');
    }
};
