<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_01_120000_create_month_closures_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-016 — Monatsfreigabe (siehe ../WorkDiary-Architecture/monatsfreigabe.md §2.1).
 *
 * Eine Zeile pro Mitarbeitenden × Kalendermonat. Hält den aktuellen Status
 * (draft → submitted → approved/rejected → reopened → locked), einen
 * immutable Totals-Snapshot (JSON) und Aggregat-Kennzahlen zur Anzeige.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('month_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');

            // draft|submitted|approved|rejected|reopened|locked
            $table->string('status', 20)->default('draft');

            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();

            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // Immutable Snapshot der Summen (Soll/Ist/Saldo/Zuschläge/Warnungen).
            // Struktur: siehe ../WorkDiary-Architecture/monatsfreigabe.md §3.
            $table->json('totals')->nullable();

            $table->unsignedSmallInteger('days_total')->default(0);
            $table->unsignedSmallInteger('days_with_attendance')->default(0);
            $table->unsignedSmallInteger('days_closed')->default(0);
            $table->unsignedSmallInteger('days_open')->default(0);
            $table->unsignedSmallInteger('warnings_count')->default(0);

            $table->timestamps();

            $table->unique(['organization_id', 'user_id', 'period_year', 'period_month'], 'month_closures_period_unique');
            $table->index(['organization_id', 'status', 'period_year', 'period_month'], 'month_closures_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('month_closures');
    }
};
