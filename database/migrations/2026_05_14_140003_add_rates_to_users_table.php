<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds default hourly + internal rates to users (used by Kimai-style rate
 * calculation when more specific overrides are not set).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('must_change_password');
            $table->decimal('internal_rate', 10, 2)->nullable()->after('hourly_rate');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['hourly_rate', 'internal_rate']);
        });
    }
};
