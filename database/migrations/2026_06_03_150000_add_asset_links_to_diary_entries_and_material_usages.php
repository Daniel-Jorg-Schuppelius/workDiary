<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_03_150000_add_asset_links_to_diary_entries_and_material_usages.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('diary_entries') && ! Schema::hasColumn('diary_entries', 'asset_id')) {
            Schema::table('diary_entries', function (Blueprint $table): void {
                $table->foreignId('asset_id')->nullable()->after('customer_id')
                    ->constrained('assets')->nullOnDelete();

                $table->index(['organization_id', 'asset_id'], 'de_org_asset_idx');
            });
        }

        if (Schema::hasTable('material_usages') && ! Schema::hasColumn('material_usages', 'asset_id')) {
            Schema::table('material_usages', function (Blueprint $table): void {
                $table->foreignId('asset_id')->nullable()->after('material_id')
                    ->constrained('assets')->nullOnDelete();

                $table->index(['organization_id', 'asset_id'], 'material_usages_org_asset_idx');
            });
        }
    }

    public function down(): void {
        Schema::table('material_usages', function (Blueprint $table): void {
            $table->dropIndex('material_usages_org_asset_idx');
            $table->dropConstrainedForeignId('asset_id');
        });

        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropIndex('de_org_asset_idx');
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
