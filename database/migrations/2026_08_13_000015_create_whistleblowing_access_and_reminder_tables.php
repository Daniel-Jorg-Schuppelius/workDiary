<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000015_create_whistleblowing_access_and_reminder_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-3-Nachzuegler (Abschnitt 7.4 / 15 / 11):
 *  - Interessenkonflikt-Selbstsperre (case_conflicts)
 *  - Notfallfreigaben mit Zweit-Genehmiger und Ablauf (emergency_grants)
 *  - idempotente Fristen-Erinnerungen (deadline_reminders)
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('whistleblowing_case_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('whistleblowing_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason_ciphertext')->nullable();
            $table->timestamp('declared_at');

            $table->unique(['case_id', 'user_id']);
        });

        Schema::create('whistleblowing_emergency_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('whistleblowing_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();         // Beguenstigter
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();       // Zweit-Genehmiger
            $table->text('reason_ciphertext');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();

            $table->index(['case_id', 'user_id', 'expires_at']);
        });

        Schema::create('whistleblowing_deadline_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('whistleblowing_cases')->cascadeOnDelete();
            $table->string('kind', 16);          // acknowledge | feedback
            $table->date('reminder_date');
            $table->timestamp('created_at')->nullable();

            $table->unique(['case_id', 'kind', 'reminder_date']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('whistleblowing_deadline_reminders');
        Schema::dropIfExists('whistleblowing_emergency_grants');
        Schema::dropIfExists('whistleblowing_case_conflicts');
    }
};
