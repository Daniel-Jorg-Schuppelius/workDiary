<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasDocumentLines.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contracts;

use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Belegkopf mit Positionen (MVP-865). `lines()` liefert die Positionen in
 * Belegreihenfolge, `documentTotals()` die Summen aus dem
 * {@see \App\Services\Billing\DocumentTotalsCalculator} — Rundung je
 * Steuersatz, Belegrabatt anteilig, Reverse Charge ohne Steuer.
 *
 * @phpstan-import-type Totals from \App\Services\Billing\DocumentTotalsCalculator
 */
interface HasDocumentLines {
    /** @return HasMany<covariant Model, covariant Model> */
    public function lines(): HasMany;

    public function documentCurrency(): CurrencyCode;

    /** @return Totals */
    public function documentTotals(): array;
}
