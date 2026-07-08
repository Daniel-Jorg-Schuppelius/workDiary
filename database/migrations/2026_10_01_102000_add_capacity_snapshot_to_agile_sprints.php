<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_102000_add_capacity_snapshot_to_agile_sprints.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 064, P10 (MVP-148): Kapazitäts-Snapshot beim Sprintstart
 * (Projektmitglieder × Arbeitszeitmodell − genehmigte Abwesenheiten ±
 * manuelle Korrektur mit Pflichtbegründung) — unveränderlich wie die
 * übrigen Snapshots.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('agile_sprints', function (Blueprint $table): void {
            $table->json('capacity_snapshot')->nullable()->after('completion_snapshot');
        });
    }

    public function down(): void {
        Schema::table('agile_sprints', function (Blueprint $table): void {
            $table->dropColumn('capacity_snapshot');
        });
    }
};
