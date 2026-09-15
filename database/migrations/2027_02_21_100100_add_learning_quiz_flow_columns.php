<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_21_100100_add_learning_quiz_flow_columns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prüfungsablauf (Feature 149, MVP-783): fragenweise Anzeige, Zurück und
 * Überspringen, Pflichtbeantwortung, Ergebnistexte je Prozentbereich —
 * und an der Antwort das Merkzeichen für die Fragenübersicht. Antworten
 * werden jetzt auch VOR der Abgabe gespeichert (Zwischenspeicher gegen
 * Verbindungsabbruch): `is_correct` bleibt dann null, Punkte 0.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('learning_quizzes', function (Blueprint $table): void {
            // all = alle Fragen auf einer Seite, single = fragenweise.
            $table->string('display_mode', 10)->default('all')->after('show_solutions');
            $table->boolean('allow_back')->default(true)->after('display_mode');
            $table->boolean('allow_skip')->default(true)->after('allow_back');
            $table->boolean('require_all_answered')->default(false)->after('allow_skip');
            // [{from_percent: int, text: string}, …]
            $table->json('result_messages')->nullable()->after('require_all_answered');
        });

        Schema::table('learning_answers', function (Blueprint $table): void {
            $table->boolean('flagged')->default(false)->after('points_awarded');
        });
    }

    public function down(): void {
        Schema::table('learning_answers', function (Blueprint $table): void {
            $table->dropColumn('flagged');
        });

        Schema::table('learning_quizzes', function (Blueprint $table): void {
            $table->dropColumn(['display_mode', 'allow_back', 'allow_skip', 'require_all_answered', 'result_messages']);
        });
    }
};
