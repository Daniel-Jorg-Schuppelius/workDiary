<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_21_100800_add_learning_course_to_survey_invitations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sternewert im Kurskatalog (Feature 149, MVP-794): die Kursfeedback-Einladung
 * merkt sich den Kurs, damit Skalenantworten je Kurs gemittelt werden können
 * — nur auf Kursebene, nie auf die Person zurückführbar (ab fünf Antworten).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('survey_invitations', function (Blueprint $table): void {
            $table->foreignId('learning_course_id')->nullable()->after('customer_id')
                ->constrained('learning_courses')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('survey_invitations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('learning_course_id');
        });
    }
};
