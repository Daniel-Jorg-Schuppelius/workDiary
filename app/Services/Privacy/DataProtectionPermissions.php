<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataProtectionPermissions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\Organization;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;

/**
 * Single Source of Truth fuer die Datenschutz-Permissions. BEWUSST getrennt von
 * der zentralen {@see \App\Enums\User\Permission}-Enum, deren Seeder dem
 * Plattform-Admin ALLE Permissions zuweist – Betroffenen-/Vorfalldaten sollen
 * aber nicht automatisch fuer Admins zugaenglich sein. Diese Permissions gehen
 * an die Rolle `datenschutz` bzw. explizit zugewiesene Personen.
 *
 * Hinweis: `privacy.*` ist anderweitig belegt (User-Self-Service) – daher der
 * Prefix `dataprotection.*`.
 */
final class DataProtectionPermissions {
    public const ROLE_DATENSCHUTZ = 'datenschutz';

    /** @var list<string> */
    public const ALL = [
        'dataprotection.view',
        'dataprotection.ropa.manage',   // VVT bearbeiten/versionieren
        'dataprotection.ropa.approve',  // VVT freigeben
        'dataprotection.avv.manage',    // Dienstleister/AVV-Register
        'dataprotection.tom.manage',    // TOM-Katalog (Art. 32)
        'dataprotection.compliance.manage', // Lueckenanalyse-Befunde entscheiden
        'dataprotection.incident.manage', // Datenschutzvorfaelle
        'dataprotection.dpia.manage',   // Datenschutz-Folgenabschaetzung
        'dataprotection.dsr.manage',    // Betroffenenanfragen bearbeiten/entscheiden
        'dataprotection.dsr.assign',    // Anfragen zuweisen
        'dataprotection.export',        // VVT-/Fall-Exporte
        'dataprotection.audit.view',    // Ereignisprotokoll einsehen
    ];

    /** Legt die Permissions global (team-unabhaengig, guard web) an. Idempotent. */
    public static function ensurePermissionsExist(): void {
        foreach (self::ALL as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /**
     * Legt fuer eine Organisation die Rolle `datenschutz` mit allen Permissions
     * an (team_id = organization.id). Idempotent.
     */
    public static function seedOrganization(Organization $organization, ?PermissionRegistrar $registrar = null): void {
        $registrar ??= app(PermissionRegistrar::class);
        self::ensurePermissionsExist();

        $registrar->setPermissionsTeamId($organization->id);
        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        /** @var Role $role */
        $role = Role::query()->firstOrCreate([
            $teamForeign => $organization->id,
            'name' => self::ROLE_DATENSCHUTZ,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(self::ALL);
    }
}
