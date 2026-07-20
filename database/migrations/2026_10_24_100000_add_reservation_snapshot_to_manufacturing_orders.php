<?php
/*
 * Created on   : Sun Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_24_100000_add_reservation_snapshot_to_manufacturing_orders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservierungsstrategie (Feature 048; Vollaudit 2026-07, M23): Der Auftrag
 * friert den angewendeten Modus und den Zeitpunkt der automatischen
 * Materialreservierung als Snapshot ein.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('manufacturing_orders', function (Blueprint $table): void {
            $table->string('reservation_mode', 20)->nullable()->after('released_at');
            $table->timestamp('reservation_applied_at')->nullable()->after('reservation_mode');
        });
    }

    public function down(): void {
        Schema::table('manufacturing_orders', function (Blueprint $table): void {
            $table->dropColumn(['reservation_mode', 'reservation_applied_at']);
        });
    }
};
