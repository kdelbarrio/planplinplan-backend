<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('events', function (Blueprint $t) {
            // Campos de ORIGEN (los actualiza el ETL/Upserter)
            $t->string('type_src')->nullable()->after('territory_src');
            $t->string('type_code_src')->nullable()->after('type_src');

            // Campo CURADO (no lo toca el ETL)
            $t->foreignId('event_type_id')->nullable()
              ->after('type_code_src')
              ->constrained('event_types')->nullOnDelete();

            $t->index('type_code_src');
            $t->index('event_type_id');
        });
    }

    public function down(): void {
        Schema::table('events', function (Blueprint $t) {
            $t->dropConstrainedForeignId('event_type_id');
            $t->dropColumn(['type_src','type_code_src']);
        });
    }
};
