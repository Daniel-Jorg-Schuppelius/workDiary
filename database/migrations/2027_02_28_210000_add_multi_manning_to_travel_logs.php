<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_210000_add_multi_manning_to_travel_logs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1014: Mehrfahrerbesatzung (Art. 8 Abs. 5) und Fähre/Zug (Art. 9 VO (EG) 561/2006) am Fahrtenbucheintrag. */
return new class extends Migration {
    public function up(): void {
        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->foreignId('co_driver_user_id')->nullable()->after('user_id')
                ->constrained('users', indexName: 'travel_logs_co_driver_fk')->nullOnDelete();
            $table->boolean('is_ferry_or_train')->default(false)->after('trip_kind');
        });
    }

    public function down(): void {
        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->dropForeign('travel_logs_co_driver_fk');
            $table->dropColumn(['co_driver_user_id', 'is_ferry_or_train']);
        });
    }
};
