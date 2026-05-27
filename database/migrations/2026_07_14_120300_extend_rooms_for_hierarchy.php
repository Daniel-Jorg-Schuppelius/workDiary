<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_14_120300_extend_rooms_for_hierarchy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->foreignId('floor_id')->nullable()->after('organization_id')
                ->constrained('floors')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('floor_id')
                ->constrained('customers')->nullOnDelete();
            $table->string('usage_type', 32)->default('office')->after('floor');
            $table->decimal('net_area_m2', 8, 2)->nullable()->after('usage_type');

            $table->index(['organization_id', 'customer_id', 'is_active'], 'rooms_idx_org_customer');
            $table->index(['floor_id'], 'rooms_idx_floor');
        });
    }

    public function down(): void {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropIndex('rooms_idx_floor');
            $table->dropIndex('rooms_idx_org_customer');
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id']);
            $table->dropForeign(['floor_id']);
            $table->dropColumn(['floor_id', 'usage_type', 'net_area_m2']);
        });
    }
};
