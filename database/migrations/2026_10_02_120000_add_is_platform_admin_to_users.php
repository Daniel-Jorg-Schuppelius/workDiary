<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_02_120000_add_is_platform_admin_to_users.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Plattform-Betreiber-Kennung (Cross-Tenant-Härtung): trennt den globalen
 * Betreiber-Admin vom org-lokalen Admin. Nur ein Plattform-Admin darf den
 * Organisations-Kontext wechseln (OrganizationSwitchController /
 * SetOrganizationContext). Spatie kann das nicht abbilden, weil
 * model_has_roles.team_id NOT NULL + Teil des Primärschlüssels ist.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('users', static function (Blueprint $table): void {
            $table->boolean('is_platform_admin')->default(false)->after('is_new_system');
        });

        // Backfill (Bestandssysteme): den frühesten Betreiber zum Plattform-
        // Admin machen, damit nach dem Deploy nicht plötzlich niemand mehr den
        // Org-Kontext wechseln oder Mandanten verwalten kann. Bevorzugt der
        // Owner der ersten Organisation, sonst der früheste Nutzer mit
        // admin-Rolle. Auf frischer DB (Installer/Tests) ein No-op.
        $operatorId = DB::table('organizations')
            ->whereNotNull('owner_id')
            ->orderBy('id')
            ->value('owner_id');

        if ($operatorId === null) {
            $operatorId = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'admin')
                ->where('model_has_roles.model_type', 'App\\Models\\User')
                ->orderBy('model_has_roles.model_id')
                ->value('model_has_roles.model_id');
        }

        if ($operatorId !== null) {
            DB::table('users')->where('id', $operatorId)->update(['is_platform_admin' => true]);
        }
    }

    public function down(): void {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn('is_platform_admin');
        });
    }
};
