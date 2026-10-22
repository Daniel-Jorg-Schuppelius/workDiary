<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_120100_add_accommodation_and_reminders_to_learning_enrollments_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nachteilsausgleich als Zeitverlängerung in Prozent (MVP-1267) und die zwei
 * Fristerinnerungen für Externe ohne Konto (MVP-1270).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('learning_enrollments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('time_extension_percent')->nullable()->after('points_earned');
            $table->timestamp('reminded_at')->nullable()->after('time_extension_percent');
            $table->timestamp('final_reminded_at')->nullable()->after('reminded_at');
        });
    }

    public function down(): void {
        Schema::table('learning_enrollments', function (Blueprint $table): void {
            $table->dropColumn(['time_extension_percent', 'reminded_at', 'final_reminded_at']);
        });
    }
};
