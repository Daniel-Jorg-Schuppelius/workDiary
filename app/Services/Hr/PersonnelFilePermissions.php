<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelFilePermissions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Hr;

use App\Services\Concerns\SeedsIsolatedPermissionSet;

/**
 * Zugriffskreis der digitalen Personalakte (Feature 141, MVP-708). BEWUSST
 * getrennt von der zentralen {@see \App\Enums\User\Permission}-Enum (Muster
 * Hinweisgebersystem/Datenschutz): Org- und Plattform-Admins erhalten die
 * Rechte NICHT automatisch — die Akte sehen nur explizit berechtigte
 * Personen (Rolle `personalakte` bzw. Direktvergabe) und die betroffene
 * Person selbst (lesend, Eigenauskunft).
 */
final class PersonnelFilePermissions {
    use SeedsIsolatedPermissionSet;

    public const string ROLE_PERSONALAKTE = 'personalakte';

    /** Trait-Vertrag ({@see SeedsIsolatedPermissionSet}). */
    public const string ROLE = self::ROLE_PERSONALAKTE;

    public const string VIEW_ANY = 'hrFile.viewAny';

    public const string CREATE = 'hrFile.create';

    public const string UPDATE = 'hrFile.update';

    public const string DELETE = 'hrFile.delete';

    /** @var list<string> */
    public const array ALL = [
        self::VIEW_ANY,
        self::CREATE,
        self::UPDATE,
        self::DELETE,
    ];
}
