<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseVoucherRef.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Carbon;

/**
 * Provider-neutraler Beleg-Verweis für den Auslagen-Dialog (Vollscan
 * 2026-08-23, B9). Trägt genau das, was Kern und Views brauchen — der
 * Provider hält die Modell-Identität bei sich ({@see $key} ist sein eigener
 * Formularschlüssel, {@see $previewUrl} seine eigene Detailansicht).
 */
final readonly class ExpenseVoucherRef {
    public function __construct(
        public string $externalId,
        public string $key,
        public ?string $number = null,
        public ?Carbon $date = null,
        public ?Money $gross = null,
        public ?CurrencyCode $currency = null,
        public ?string $partyName = null,
        public ?string $previewUrl = null,
    ) {}

    /** Betrag als float für die Formatierung in der View (0.0 = unbekannt). */
    public function grossFloat(): float {
        return $this->gross?->toFloat() ?? 0.0;
    }

    public function currencyLabel(): string {
        return $this->currency->value ?? '';
    }
}
