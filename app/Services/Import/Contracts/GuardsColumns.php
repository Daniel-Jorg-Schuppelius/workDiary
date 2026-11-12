<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuardsColumns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Contracts;

use App\Enums\User\Permission;

/**
 * Export- oder Import-Spec mit Spalten, die außerhalb der Datenübertragung ein
 * eigenes Recht verlangen (MVP-1625), etwa Verrechnungs- und Kostensätze.
 * Der Export lässt sie ohne das Recht weg, der Import übergeht sie.
 */
interface GuardsColumns {
    /** @return array<string, Permission> Spalten-Code ⇒ nötiges Recht */
    public function columnPermissions(): array;
}
