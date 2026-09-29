<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_212000_add_fee_to_club_event_details.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1017: Gebühr eines Lehrgangs bzw. einer Prüfung am Vereinstermin. */
return new class extends Migration {
    public function up(): void {
        Schema::table('club_event_details', function (Blueprint $table): void {
            $table->decimal('fee_amount', 12, 2)->nullable()->after('cancellation_lead_hours');
            $table->char('currency', 3)->default('EUR')->after('fee_amount');
        });
    }

    public function down(): void {
        Schema::table('club_event_details', function (Blueprint $table): void {
            $table->dropColumn(['fee_amount', 'currency']);
        });
    }
};
