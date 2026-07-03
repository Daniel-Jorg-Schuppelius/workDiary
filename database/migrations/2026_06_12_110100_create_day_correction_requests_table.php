<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_12_110100_create_day_correction_requests_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-015 — Korrekturanträge zum Tagesabschluss (../WorkDiary-Architecture/tagesabschluss.md §5).
 *
 * Org-scoped (eigene organization_id + BelongsToOrganization) statt reiner
 * Kind-Datensatz — konsistent mit time_correction_requests (MVP-017), damit
 * der OrganizationScope mandantenübergreifende Zugriffe hart verhindert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('day_correction_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('day_closure_id')
                ->constrained('day_closures')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->text('reason');

            // pending|approved|rejected
            $table->string('status', 20)->default('pending');

            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();

            $table->timestamps();

            // Kurze, explizite Index-Namen (MySQL-64-Zeichen-Limit).
            $table->index(['organization_id', 'status'], 'day_corr_requests_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('day_correction_requests');
    }
};
