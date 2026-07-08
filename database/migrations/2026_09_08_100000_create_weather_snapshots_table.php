<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_08_100000_create_weather_snapshots_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wetter-Snapshot je Ort und Tag (Feature 062, MVP-131): unveränderlicher,
 * historisierter Messwert (Temperatur min/max, Niederschlag, Windspitze,
 * Wetterlage) mit Quelle und Abrufzeitpunkt — beweisrelevant fürs Bautagebuch.
 * Idempotent über (Org, Koordinate, Tag, Provider): einmal abgerufen, nie
 * überschrieben. Die manuelle Vor-Ort-Beobachtung bleibt ein eigenes Feld
 * anderswo (Messwert ≠ Beobachtung).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('weather_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'weathersnap_org_fk')->cascadeOnDelete();
            $table->decimal('geo_lat', 9, 6);
            $table->decimal('geo_lng', 9, 6);
            $table->date('snapshot_date');
            $table->string('provider', 32);
            $table->timestamp('fetched_at');
            $table->decimal('temp_min', 5, 2)->nullable();
            $table->decimal('temp_max', 5, 2)->nullable();
            $table->decimal('precipitation_mm', 6, 2)->nullable();
            $table->decimal('wind_gust_kmh', 6, 2)->nullable();
            $table->unsignedSmallInteger('weather_code')->nullable();
            $table->json('raw');                 // Rohantwort des Providers (Nachweis)
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'weathersnap_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'geo_lat', 'geo_lng', 'snapshot_date', 'provider'], 'weathersnap_unique');
            $table->index(['organization_id', 'snapshot_date'], 'weathersnap_org_date_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('weather_snapshots');
    }
};
