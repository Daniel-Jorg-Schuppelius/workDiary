<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_21_100400_create_learning_course_trainers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trainer je Kurs (Feature 149, MVP-786): wer einen Kurs betreut oder
 * bewertet. Wirkt nur mit dem Org-Schalter `learning.scope_to_courses` —
 * dann sehen Autoren und Bewertende ohne `learning.manage` nur ihre Kurse.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_course_trainers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_course_id')->constrained('learning_courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // trainer | grader
            $table->string('role', 10)->default('trainer');
            $table->timestamps();

            $table->unique(['learning_course_id', 'user_id'], 'lrn_trainer_course_user_uq');
            $table->index(['user_id'], 'lrn_trainer_user_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_course_trainers');
    }
};
