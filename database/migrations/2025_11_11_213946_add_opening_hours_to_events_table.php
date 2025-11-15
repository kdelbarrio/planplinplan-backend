<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Ajusta la posición si quieres (después de ends_at, por ejemplo)
            $table->string('opening_hours', 255)->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('opening_hours');
        });
    }
};

