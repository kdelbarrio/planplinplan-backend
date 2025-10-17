<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('provinces', function (Blueprint $t) {
            $t->id();
            $t->integer('province_id')->unique();   // p.ej. 48, 20, 31, -2, -3, 1
            $t->string('name_es', 120);
            $t->string('name_eu', 120);
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('provinces');
    }
};