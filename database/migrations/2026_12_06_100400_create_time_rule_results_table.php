<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_100400_create_time_rule_results_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-513 P2 (Feature 103): persistierte Regel-Ergebnisse je Zeitdatensatz
 * mit Berechnungs-Snapshot — nachvollziehbar, welcher Regelstand welches
 * Ergebnis erzeugt hat. Abgeleitete Daten (reproduzierbar); das
 * revisionssichere Original bleibt der Zeitexport.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('time_rule_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->nullOnDelete();
            $table->foreignId('surcharge_rule_id')->constrained('surcharge_rules')->cascadeOnDelete();
            $table->foreignId('time_export_id')->nullable()->constrained('time_exports')->nullOnDelete();
            $table->date('date');
            $table->unsignedInteger('minutes');
            $table->string('wage_type_code', 40);
            $table->decimal('percentage', 5, 2);
            $table->json('calculation_snapshot');
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'date'], 'trr_org_user_date_idx');
            $table->index(['time_export_id'], 'trr_export_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('time_rule_results');
    }
};
