<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_130000_create_feature_usage_counters_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telemetry-Light (Feature 036): aggregierte Feature-Nutzungszähler pro
 * Organisation, Feature und Tag. KEINE personenbezogenen Daten, kein
 * Einzel-User-Tracking — nur Org-Tagesaggregate, lokal gespeichert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('feature_usage_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('feature', 80);
            $table->date('period_date');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            // Kurze, explizite Namen (MySQL-64-Zeichen-Limit, SQLite verdeckt das).
            $table->unique(['organization_id', 'feature', 'period_date'], 'fuc_org_feature_period_uq');
            $table->index(['organization_id', 'period_date'], 'fuc_org_period_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('feature_usage_counters');
    }
};
