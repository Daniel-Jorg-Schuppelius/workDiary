<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_206000_add_checkin_code_to_club_event_details.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1004: Termincode für den QR-Selbst-Check-in. */
return new class extends Migration {
    public function up(): void {
        Schema::table('club_event_details', function (Blueprint $table): void {
            $table->string('checkin_code', 40)->nullable()->unique()->after('cancellation_lead_hours');
        });
    }

    public function down(): void {
        Schema::table('club_event_details', function (Blueprint $table): void {
            $table->dropUnique(['checkin_code']);
            $table->dropColumn('checkin_code');
        });
    }
};
