<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fixture für PluginSchemaLifecycleTest (Review 2026-08, W6). */
return new class extends Migration {
    public function up(): void {
        Schema::create('wd_schema_probe', function (Blueprint $table): void {
            $table->id();
            $table->string('value')->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('wd_schema_probe');
    }
};
