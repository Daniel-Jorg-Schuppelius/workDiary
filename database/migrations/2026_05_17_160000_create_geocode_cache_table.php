<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_17_160000_create_geocode_cache_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geocode cache (TR2):
 *  - Globally scoped (no organization_id) — coordinates are public data.
 *  - `query_hash` = sha256 of the lowercased, trimmed query string,
 *    so identical lookups hit the cache regardless of caller.
 *  - `expires_at` lets us age out stale entries via a future GC command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geocode_cache', function (Blueprint $table): void {
            $table->id();
            $table->string('query_hash', 64)->unique();
            $table->string('query', 500);
            $table->string('address_formatted', 500)->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('provider', 32)->default('nominatim');
            $table->json('raw')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocode_cache');
    }
};
