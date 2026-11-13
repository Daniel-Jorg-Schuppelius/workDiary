<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_18_500000_add_privacy_approval_to_ai_provider_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datenschutzfreigabe je KI-Verbindung (Feature 025, MVP-1672): AVV liegt vor
 * (Dokument als Anhang), Verarbeitung in der EU, keine Trainingsnutzung,
 * geprüft am und von. Bestandsverbindungen starten ohne Freigabe; ob das die
 * KI sperrt, entscheidet die Organisationseinstellung
 * `ai.privacy.require_approval` (Bestand: aus, Migration 500100).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('ai_provider_connections', function (Blueprint $table): void {
            $table->boolean('has_dpa')->default(false);
            $table->boolean('is_eu_processing')->default(false);
            $table->boolean('is_training_excluded')->default(false);
            $table->dateTime('privacy_reviewed_at')->nullable();
            $table->foreignId('privacy_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('ai_provider_connections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('privacy_reviewed_by');
            $table->dropColumn(['has_dpa', 'is_eu_processing', 'is_training_excluded', 'privacy_reviewed_at']);
        });
    }
};
