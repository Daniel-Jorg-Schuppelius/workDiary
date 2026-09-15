<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_21_100700_add_learning_question_refinements.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fragen-Feinheiten (Feature 149, MVP-793): Punkte je Antwortoption,
 * Prozent-Teilmenge und Bestehensgrenze in Punkten je Prüfung. Die
 * Selbsteinschätzung und der Aufsatz-Upload leben in `settings` der Frage.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('learning_question_options', function (Blueprint $table): void {
            // NULL = Punkte der Frage gelten (alles-oder-nichts); sonst je Option.
            $table->smallInteger('points')->nullable()->after('is_correct');
        });

        Schema::table('learning_quizzes', function (Blueprint $table): void {
            // Alternative zur festen Anzahl: Anteil der verfügbaren Fragen je Versuch.
            $table->unsignedTinyInteger('questions_per_attempt_percent')->nullable()->after('questions_per_attempt');
            // Bestehen in Punkten statt Prozent — beides gesetzt: beides muss erreicht sein.
            $table->unsignedInteger('pass_points')->nullable()->after('pass_percent');
        });
    }

    public function down(): void {
        Schema::table('learning_quizzes', function (Blueprint $table): void {
            $table->dropColumn(['questions_per_attempt_percent', 'pass_points']);
        });
        Schema::table('learning_question_options', function (Blueprint $table): void {
            $table->dropColumn('points');
        });
    }
};
