<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('event_types', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();      // p.ej. "teatro", "musica-pop"
            $t->string('name');                // etiqueta canónica visible (ES u otra)
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('event_types');
    }
};
