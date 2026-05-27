<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_14_120400_add_room_id_to_assets_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('assets', function (Blueprint $table): void {
            $table->foreignId('room_id')->nullable()->after('customer_id')
                ->constrained('rooms')->nullOnDelete();
            $table->index(['room_id'], 'assets_idx_room');
        });
    }

    public function down(): void {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropIndex('assets_idx_room');
            $table->dropForeign(['room_id']);
            $table->dropColumn('room_id');
        });
    }
};
