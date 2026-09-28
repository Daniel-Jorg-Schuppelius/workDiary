<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesOwnAccount.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance\Concerns;

use App\Models\Accounting\AccountingAccount;
use App\Models\Platform\Organization;
use App\Support\Sqid;

/** Konto aus Formularwert (Sqid oder ID) — nur eines der eigenen Organisation, sonst 422. */
trait ResolvesOwnAccount {
    private function ownAccountId(Organization $organization, mixed $raw): ?int {
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = (int) Sqid::decodeOrNumeric(AccountingAccount::class, (string) $raw);
        abort_unless(AccountingAccount::query()->where('organization_id', $organization->id)->whereKey($id)->exists(), 422);

        return $id;
    }
}
