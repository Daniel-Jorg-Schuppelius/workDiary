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

use App\Services\Concerns\SeedsIsolatedPermissionSet;

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
    use SeedsIsolatedPermissionSet;

    public const ROLE_DATENSCHUTZ = 'datenschutz';

    /** Trait-Vertrag ({@see SeedsIsolatedPermissionSet}). */
    public const ROLE = self::ROLE_DATENSCHUTZ;

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
}
