<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('etl_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('inserted')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('etl_runs');
    }
};

