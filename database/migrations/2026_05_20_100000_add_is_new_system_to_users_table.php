<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_20_100000_add_is_new_system_to_users_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_new_system')->default(false)->after('legacy_user_id');
            $table->index('is_new_system');
        });

        // Backfill: Bestehende Accounts, die aktiv im neuen System sein sollen,
        // als "im neuen System" markieren.
        //  - Alle User ohne legacy_user_id (rein neue Accounts)
        //  - Alle User, denen eine Spatie-Rolle zugewiesen wurde (admin/user/buchhaltung/callcenter)
        // Schatten-Accounts, die ausschließlich durch Legacy-Login entstanden sind
        // und keine Rolle haben, bleiben auf false → kein Zugriff auf neue Funktionen.
        DB::table('users')->whereNull('legacy_user_id')->update(['is_new_system' => true]);

        if (Schema::hasTable('model_has_roles')) {
            $userIds = DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->pluck('model_id')
                ->unique()
                ->all();

            if (! empty($userIds)) {
                DB::table('users')->whereIn('id', $userIds)->update(['is_new_system' => true]);
            }
        }
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_new_system']);
            $table->dropColumn('is_new_system');
        });
    }
};
