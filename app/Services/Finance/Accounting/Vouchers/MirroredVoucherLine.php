<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirroredVoucherLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

/**
 * Eine Belegposition, wie ein Puller sie liest (Feature 185, MVP-1424).
 * Beträge als Dezimalzeichenketten in der Belegwährung, netto.
 */
final readonly class MirroredVoucherLine {
    public function __construct(
        public int $position,
        public string $name,
        public float $quantity,
        public string $unitNet,
        public string $totalNet,
        public string $currency = 'EUR',
        public ?string $externalArticleId = null,
        /** Katalogschlüssel (`art:<id>`, …), wenn der Puller den Artikel kennt. */
        public ?string $articleRef = null,
        public ?string $description = null,
        public ?string $unitName = null,
        public ?string $taxRate = null,
    ) {}
}
