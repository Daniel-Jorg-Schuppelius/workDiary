<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingPermissions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Models\Organization;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;

/**
 * Single Source of Truth fuer die Hinweisgeber-Permissions. BEWUSST getrennt
 * von der zentralen {@see \App\Enums\User\Permission}-Enum, deren Seeder dem
 * Plattform-Admin ALLE Permissions zuweist – Meldeinhalte sollen jedoch nie
 * automatisch fuer Admins zugaenglich sein (Abschnitt 5 / 25). Diese Permissions
 * gehen ausschliesslich an die Rolle `meldestelle` bzw. explizit zugewiesene
 * Personen.
 */
final class WhistleblowingPermissions {
    public const ROLE_MELDESTELLE = 'meldestelle';

    /** @var list<string> */
    public const ALL = [
        'whistleblowing.settings.manage',
        'whistleblowing.case.viewAny',
        'whistleblowing.case.view',
        'whistleblowing.case.process',
        'whistleblowing.case.assign',
        'whistleblowing.case.emergency',
        'whistleblowing.case.message',
        'whistleblowing.case.note',
        'whistleblowing.case.export',
        'whistleblowing.case.close',
        'whistleblowing.case.retention',
        'whistleblowing.audit.view',
    ];

    /** Legt die Permissions global (team-unabhaengig, guard web) an. Idempotent. */
    public static function ensurePermissionsExist(): void {
        foreach (self::ALL as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /**
     * Legt fuer eine Organisation die Rolle `meldestelle` mit allen Fall-
     * Permissions an (team_id = organization.id). Idempotent.
     */
    public static function seedOrganization(Organization $organization, ?PermissionRegistrar $registrar = null): void {
        $registrar ??= app(PermissionRegistrar::class);
        self::ensurePermissionsExist();

        $registrar->setPermissionsTeamId($organization->id);
        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        /** @var Role $role */
        $role = Role::query()->firstOrCreate([
            $teamForeign => $organization->id,
            'name' => self::ROLE_MELDESTELLE,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(self::ALL);
    }
}
