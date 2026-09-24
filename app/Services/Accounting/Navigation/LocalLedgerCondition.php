<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocalLedgerCondition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Navigation;

use App\Models\Platform\{Organization, User};
use App\Services\Accounting\AccountingSovereigntyResolver;
use App\Services\Navigation\Contracts\NavigationCondition;

/** Einträge der lokalen Buchhaltung nur, wenn die Organisation ein eigenes Hauptbuch führt. */
final class LocalLedgerCondition implements NavigationCondition {
    public function __construct(private readonly AccountingSovereigntyResolver $sovereignty) {}

    public function key(): string {
        return 'accounting.local_ledger';
    }

    public function passes(?User $user, ?Organization $organization): bool {
        return $organization !== null && $this->sovereignty->hasLocalLedger($organization);
    }
}
