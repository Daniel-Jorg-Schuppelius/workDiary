<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_105400_add_course_completion_trigger_to_surveys.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kursfeedback über die vorhandene Umfrage-Engine (Feature 149, MVP-747).
 *
 * Kein zweites Umfragewerkzeug: Feature 090 bringt bereits Fragen,
 * Einladungen, Anonymität und Ermüdungsschutz mit. Hier kommt nur ein
 * weiterer Anlass dazu — wie `trigger_on_ticket_close`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->boolean('trigger_on_course_completion')->default(false)->after('trigger_on_ticket_close');
        });
    }

    public function down(): void {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->dropColumn('trigger_on_course_completion');
        });
    }
};
