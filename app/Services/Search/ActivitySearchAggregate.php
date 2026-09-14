<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivitySearchAggregate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use App\Support\CarbonFmt;
use Carbon\CarbonInterface;

/** Trefferzahl und Zeitraum je Kunde/Endkunde — beantwortet „bei wem war das?". */
final class ActivitySearchAggregate {
    public function __construct(
        public readonly ?int $customerId,
        public readonly ?string $customerName,
        public readonly ?int $foreignCustomerId,
        public readonly ?string $foreignCustomerName,
        public readonly int $hits,
        public readonly ?CarbonInterface $firstAt,
        public readonly ?CarbonInterface $lastAt,
    ) {}

    public function label(): ?string {
        $label = implode(' › ', array_filter([$this->customerName, $this->foreignCustomerName], static fn(?string $v): bool => $v !== null && $v !== ''));

        return $label !== '' ? $label : null;
    }

    public function periodLabel(): ?string {
        if ($this->firstAt === null || $this->lastAt === null) {
            return null;
        }

        $first = CarbonFmt::fdate(CarbonFmt::orgTz($this->firstAt));
        $last = CarbonFmt::fdate(CarbonFmt::orgTz($this->lastAt));

        return $first === $last ? $first : $first . ' – ' . $last;
    }
}
