<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_14_140100_add_cleaning_profile_id_to_rooms.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->foreignId('cleaning_profile_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('cleaning_profiles')
                ->nullOnDelete();

            $table->index(['organization_id', 'cleaning_profile_id'], 'rooms_idx_org_cleaning_profile');
        });
    }

    public function down(): void {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropIndex('rooms_idx_org_cleaning_profile');
            $table->dropConstrainedForeignId('cleaning_profile_id');
        });
    }
};
