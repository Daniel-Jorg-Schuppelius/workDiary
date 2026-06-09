<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000016_create_whistleblowing_case_subjects_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strukturierte Erfassung benannter Betroffener/Beschuldigter eines Falls
 * (Abschnitt 7.4): durch Bearbeiter markierte interne Benutzer, die NICHT
 * zugewiesen werden duerfen und keinen Zugriff auf den Fall erhalten.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('whistleblowing_case_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('whistleblowing_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note_ciphertext')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['case_id', 'user_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('whistleblowing_case_subjects');
    }
};
