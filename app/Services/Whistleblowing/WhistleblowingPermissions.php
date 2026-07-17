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

use App\Services\Concerns\SeedsIsolatedPermissionSet;

/**
 * Single Source of Truth fuer die Hinweisgeber-Permissions. BEWUSST getrennt
 * von der zentralen {@see \App\Enums\User\Permission}-Enum, deren Seeder dem
 * Plattform-Admin ALLE Permissions zuweist – Meldeinhalte sollen jedoch nie
 * automatisch fuer Admins zugaenglich sein (Abschnitt 5 / 25). Diese Permissions
 * gehen ausschliesslich an die Rolle `meldestelle` bzw. explizit zugewiesene
 * Personen.
 */
final class WhistleblowingPermissions {
    use SeedsIsolatedPermissionSet;

    public const ROLE_MELDESTELLE = 'meldestelle';

    /** Trait-Vertrag ({@see SeedsIsolatedPermissionSet}). */
    public const ROLE = self::ROLE_MELDESTELLE;

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
}
