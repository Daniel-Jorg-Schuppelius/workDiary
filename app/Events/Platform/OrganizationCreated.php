<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationCreated.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Platform;

use App\Models\Platform\Organization;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Organisation angelegt: Module legen ihre Erstausstattung an (Rollen,
 * Rechtekreise). Synchron in der Transaktion — die Rollen müssen existieren,
 * bevor der Aufrufer sie vergibt.
 */
final class OrganizationCreated {
    use Dispatchable;

    public function __construct(public readonly Organization $organization) {}
}
