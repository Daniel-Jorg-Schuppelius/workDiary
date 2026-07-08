<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_13_100000_add_deactivated_at_to_users_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konto-Deaktivierung (Feature 057, MVP-121): zentrales Offboarding über
 * Verzeichnisdienste (SCIM `active=false`). Ein deaktivierter Nutzer kann sich
 * nicht mehr anmelden ({@see \App\Legacy\Auth\LegacyUserProvider}); fachliche
 * Daten bleiben gemäß Datenlebenszyklus erhalten (kein Löschen). Konvention wie
 * `organizations.deactivated_at`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('deactivated_at')->nullable()->after('is_new_system');
            $table->index('deactivated_at', 'users_deactivated_at_idx');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_deactivated_at_idx');
            $table->dropColumn('deactivated_at');
        });
    }
};
