<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_110100_add_triggers_to_surveys_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umfrage-Auslöser über Ticket und Kurs hinaus (MVP-1293): nach
 * Auftragsabschluss und im festen Abstand an aktive Kunden.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->boolean('trigger_on_order_completion')->default(false)->after('trigger_on_course_completion');
            $table->unsignedSmallInteger('trigger_schedule_days')->nullable()->after('trigger_on_order_completion');
        });
    }

    public function down(): void {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->dropColumn(['trigger_on_order_completion', 'trigger_schedule_days']);
        });
    }
};
