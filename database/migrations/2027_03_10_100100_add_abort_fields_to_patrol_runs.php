<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_10_100100_add_abort_fields_to_patrol_runs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abbruch eines laufenden Rundgangs (Feature 089): Begründung und wer
 * abgebrochen hat. Der Zeitpunkt steht wie beim Abschluss in `finished_at`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('patrol_runs', function (Blueprint $table): void {
            $table->string('abort_reason', 1000)->nullable()->after('deviation_note');
            $table->foreignId('aborted_by_user_id')->nullable()->after('abort_reason')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('patrol_runs', function (Blueprint $table): void {
            $table->dropForeign(['aborted_by_user_id']);
            $table->dropColumn(['abort_reason', 'aborted_by_user_id']);
        });
    }
};
