<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_21_100300_create_learning_quiz_attempt_waivers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versuchsfreigabe (Feature 149, MVP-785): ein zusätzlicher Versuch trotz
 * Versuchsgrenze oder Sperrfrist — genau einmal, mit Begründung und Person.
 * Die Freigabe ändert die Prüfung nicht, sie gilt für EINE Einschreibung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_quiz_attempt_waivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            $table->foreignId('learning_quiz_id')->constrained('learning_quizzes')->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['learning_enrollment_id', 'learning_quiz_id'], 'lrn_waiver_enr_quiz_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_quiz_attempt_waivers');
    }
};
