<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_120000_add_question_time_limit_to_learning_quizzes_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Zeitlimit je Frage (MVP-1267): Sekunden je Frage, null = kein Takt. */
return new class extends Migration {
    public function up(): void {
        Schema::table('learning_quizzes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('question_time_limit_seconds')->nullable()->after('time_limit_minutes');
        });
    }

    public function down(): void {
        Schema::table('learning_quizzes', function (Blueprint $table): void {
            $table->dropColumn('question_time_limit_seconds');
        });
    }
};
