<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('event_type_aliases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_type_id')->nullable()
                ->constrained('event_types')->nullOnDelete();

            $t->string('source', 64);          // "kulturklik", "otra_api"
            $t->string('source_code', 64);     // código/texto de la fuente (normaliza a string)
            $t->string('source_label', 255)->nullable();
            $t->unsignedTinyInteger('confidence')->default(100); // 0..100 opcional
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(['source','source_code']);
            $t->index('event_type_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('event_type_aliases');
    }
};
