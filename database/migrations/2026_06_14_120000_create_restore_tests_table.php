<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_120000_create_restore_tests_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restore-Test-Register (Feature 017): systemweites Protokoll durchgeführter
 * Wiederherstellungs-Tests. Plattformweit (kein organization_id) — analog
 * BackupHeartbeat, da der externe Backup-/Restore-Vorgang ohne Tenant-Kontext
 * stattfindet. performed_by_user_id ist nullable (FK-frei), damit eine
 * spätere Nutzer-Löschung das revisionsrelevante Protokoll nicht reißt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('restore_tests', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 191);
            $table->date('tested_on');
            $table->string('result', 16); // passed | failed | partial
            $table->string('scope', 191)->nullable();
            $table->unsignedBigInteger('restored_size_bytes')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->date('next_due_on')->nullable();
            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tested_on', 'restore_tests_tested_on_idx');
            $table->index(['result', 'tested_on'], 'restore_tests_result_tested_idx');
            $table->index('next_due_on', 'restore_tests_next_due_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('restore_tests');
    }
};
