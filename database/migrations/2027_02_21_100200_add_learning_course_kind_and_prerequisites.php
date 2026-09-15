<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_21_100200_add_learning_course_kind_and_prerequisites.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prüfung ohne Kurs und Voraussetzungen (Feature 149, MVP-784).
 *
 * Eine „Prüfung ohne Kurs" ist ein Kurs der Art `exam` mit genau einer
 * Prüfungseinheit — derselbe Versuchs- und Nachweispfad, kein zweiter Kern.
 * `exam_for_course_id` zeigt auf den Kurs, den das Bestehen anrechnet.
 * Voraussetzungen sperren den Start, nie die Zuweisung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('learning_courses', function (Blueprint $table): void {
            // course | exam
            $table->string('kind', 10)->default('course')->after('status');
            $table->foreignId('exam_for_course_id')->nullable()->after('kind')
                ->constrained('learning_courses')->nullOnDelete();
            // all | any — wie die Voraussetzungen zusammenwirken.
            $table->string('prerequisite_mode', 4)->default('all')->after('exam_for_course_id');
        });

        Schema::create('learning_course_prerequisites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_course_id')->constrained('learning_courses')->cascadeOnDelete();
            $table->foreignId('required_course_id')->constrained('learning_courses')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['learning_course_id', 'required_course_id'], 'lrn_prereq_course_req_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_course_prerequisites');

        Schema::table('learning_courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('exam_for_course_id');
            $table->dropColumn(['kind', 'prerequisite_mode']);
        });
    }
};
