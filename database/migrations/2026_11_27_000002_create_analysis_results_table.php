<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hasil analisis per entri (docs/ai-contract.md bagian 2, docs/api-mapping.md bagian 1.2).
     */
    public function up(): void
    {
        Schema::create('analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('risk_signal', 10)->nullable();
            $table->string('tier', 10)->nullable();
            $table->boolean('safety_flag')->default(false);
            $table->json('emotions')->nullable();
            $table->string('reason', 200)->nullable();
            $table->text('summary')->nullable();
            $table->json('suggestions')->nullable();
            $table->string('source', 20)->nullable();
            $table->string('prompt_version', 20)->nullable();
            $table->string('model')->nullable();
            $table->timestamp('entry_updated_at');
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_results');
    }
};
