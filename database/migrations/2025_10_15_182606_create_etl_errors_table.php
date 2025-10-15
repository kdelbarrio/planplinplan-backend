<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('etl_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etl_run_id')->constrained('etl_runs')->cascadeOnDelete();
            $table->string('source', 50)->index();
            $table->string('external_id', 191)->nullable();
            $table->text('payload_excerpt')->nullable();  // recorte (no guardes JSON gigante)
            $table->text('error_message');
            $table->timestamps();

            $table->index(['etl_run_id', 'source']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('etl_errors');
    }
};

