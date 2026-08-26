<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_102700_create_weather_warnings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wetterwarnungen für disponierte Einsätze (Feature 062, MVP-716 — Vollscan G15).
 *
 * Eine Zeile je (Einsatz, Vorhersagetag, Schwelle) — der Unique-Key ist der
 * Dedupe-Schlüssel: die Benachrichtigung hängt als Subjekt an dieser Zeile,
 * das notification_dispatch_log dedupliziert damit genau EINE Meldung je
 * Einsatz+Tag+Schwelle. Vorhersagen werden bewusst NICHT als
 * weather_snapshots gespeichert: Snapshots sind unveränderliche Ist-Messwerte
 * (Beweiswert), eine Vorhersage ändert sich mit jedem Modelllauf. Hier liegt
 * nur der Auslösewert samt Vorhersagezeile als Nachweis, warum gewarnt wurde.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('weather_warnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'weatherwarn_org_fk')->cascadeOnDelete();
            $table->foreignId('diary_entry_id')->constrained('diary_entries', indexName: 'weatherwarn_entry_fk')->cascadeOnDelete();
            $table->date('forecast_date');
            $table->string('threshold', 16);            // WeatherWarningThreshold
            $table->decimal('value', 6, 2);              // Vorhersagewert, der die Schwelle riss
            $table->decimal('limit_value', 6, 2);        // wirksame Org-Schwelle zum Zeitpunkt der Warnung
            $table->string('provider', 32);
            $table->json('forecast');                    // Vorhersagezeile des Tages (Nachweis)
            $table->timestamps();

            $table->unique(['diary_entry_id', 'forecast_date', 'threshold'], 'weatherwarn_entry_day_threshold_uq');
            $table->index(['organization_id', 'forecast_date'], 'weatherwarn_org_date_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('weather_warnings');
    }
};
