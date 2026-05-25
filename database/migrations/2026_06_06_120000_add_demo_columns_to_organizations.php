<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_06_120000_add_demo_columns_to_organizations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('is_demo')->default(false)->after('is_active');
            $table->timestamp('demo_seeded_at')->nullable()->after('is_demo');
            $table->index('is_demo', 'idx_organizations_is_demo');
        });
    }

    public function down(): void {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex('idx_organizations_is_demo');
            $table->dropColumn(['is_demo', 'demo_seeded_at']);
        });
    }
};
