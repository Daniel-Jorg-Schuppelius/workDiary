<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_180100_grant_split_inventory_permissions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Getrennte Lagerrechte (MVP-1585): Zählen, Reservieren und Kompensieren lagen
 * bisher in `inventory.post`, die Freigabe der Inventurdifferenzen in
 * `inventory.configure`. Wer das alte Recht hält — Rolle oder Person, auch in
 * eigenen Rollen einer Organisation —, behält damit, was er durfte.
 */
return new class extends Migration {
    /** @var array<string, list<string>> altes Recht → daraus herausgelöste Rechte */
    private const array SPLIT = [
        'inventory.post' => ['inventory.count', 'inventory.reserve', 'inventory.externalResolve'],
        'inventory.configure' => ['inventory.countApprove'],
    ];

    public function up(): void {
        $now = now();
        foreach (self::SPLIT as $old => $new) {
            $oldId = DB::table('permissions')->where('name', $old)->where('guard_name', 'web')->value('id');
            if ($oldId === null) {
                continue;
            }
            foreach ($new as $name) {
                DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
                $newId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');

                foreach (DB::table('role_has_permissions')->where('permission_id', $oldId)->pluck('role_id') as $roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $newId, 'role_id' => $roleId]);
                }
                foreach (DB::table('model_has_permissions')->where('permission_id', $oldId)->get() as $grant) {
                    DB::table('model_has_permissions')->insertOrIgnore(['permission_id' => $newId] + array_diff_key((array) $grant, ['permission_id' => true]));
                }
            }
        }
    }

    public function down(): void {
        DB::table('permissions')->where('guard_name', 'web')->whereIn('name', array_merge(...array_values(self::SPLIT)))->delete();
    }
};
