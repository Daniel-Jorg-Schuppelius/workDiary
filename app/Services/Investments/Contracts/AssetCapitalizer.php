<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetCapitalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Investments\Contracts;

use App\Models\Investments\InvestmentCase;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Investition als Anlage aktivieren (MVP-909): definiert von den
 * Investitionen, gebunden von der Anlagenbuchhaltung
 * ({@see \App\Services\Accounting\InvestmentAssetCapitalizer}). Null-Bindung:
 * nicht verfügbar.
 */
interface AssetCapitalizer {
    /** Kann die Organisation Anlagen führen? */
    public function available(): bool;

    /**
     * Legt die Anlage an; Quelle ist die Investition.
     *
     * @param  array{name: string, acquired_on: string, acquisition_cost: string, useful_life_months: int}  $data
     */
    public function capitalize(InvestmentCase $case, User $actor, array $data): Model;
}
