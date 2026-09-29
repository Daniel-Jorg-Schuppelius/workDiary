<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerVoucherRef.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Carbon;

/**
 * Provider-neutraler Verweis auf eine Pauschalrechnung im Buchhaltungsprogramm
 * (MVP-1027) — Gegenstück zu {@see ExpenseVoucherRef}. {@see $key} ist der
 * Formularschlüssel des Plugins.
 */
final readonly class RetainerVoucherRef {
    public function __construct(
        public string $externalId,
        public string $key,
        public ?string $number = null,
        public ?Carbon $date = null,
        public ?Money $net = null,
        public ?Money $gross = null,
        public bool $settled = false,
    ) {}
}
