<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            // Identidad e idempotencia
            $table->string('source', 50);                       // p.ej. 'kulturklik', 'agenda_turismo'
            $table->string('source_id', 191)->nullable();       // id externo si existe
            $table->string('checksum', 191)->nullable();        // si no hay source_id, usamos checksum(title|date|venue)
            $table->unique(['source', 'source_id']);            // permite múltiples fuentes, mismo id propio en cada una
            $table->unique(['source', 'checksum']);             // para deduplicar cuando no hay source_id

            // Campos de ORIGEN (_src) – NO se pisan los *_cur en import
            $table->string('title_src', 500)->nullable();
            $table->text('description_src')->nullable();
            $table->timestamp('starts_at')->nullable()->index();   // de origen
            $table->timestamp('ends_at')->nullable();
            $table->string('venue_name_src', 255)->nullable();
            $table->string('municipality_src', 120)->nullable();
            $table->string('territory_src', 120)->nullable();
            $table->decimal('price_min_src', 8, 2)->nullable();
            $table->string('price_desc_src', 255)->nullable();
            $table->string('organizer_src', 255)->nullable();
            $table->string('source_url', 1024)->nullable();
            $table->string('image_url', 1024)->nullable();

            // Campos CURADOS (_cur) – editor
            $table->string('title_cur', 500)->nullable();
            $table->text('description_cur')->nullable();
            $table->string('venue_name_cur', 255)->nullable();
            $table->string('municipality_cur', 120)->nullable();
            $table->string('territory_cur', 120)->nullable();
            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
            $table->json('accessibility_tags')->nullable();     // p.ej. ["ramp","subtitles"]
            $table->boolean('is_indoor')->nullable();

            // Estados y reglas de negocio
            $table->boolean('is_canceled')->default(false)->index();
            $table->enum('moderation', ['pendiente','aprobado','rechazado'])->default('pendiente')->index();
            $table->boolean('visible')->default(false)->index();           // solo publica visible = true
            $table->enum('import_status', ['new','updated','unchanged','rejected'])->default('new')->index();

            // Trazabilidad
            $table->timestamp('last_source_at')->nullable();    // fecha/hora del dato origen más reciente
            $table->timestamps();
            $table->softDeletes();

            // Índices útiles para listados públicos
            $table->index(['visible', 'starts_at']);
            $table->index(['municipality_cur', 'visible', 'starts_at']);
            $table->index(['territory_cur', 'visible', 'starts_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('events');
    }
};
