<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\{Customer, ForeignCustomer};

/** Ergebnis eines eindeutigen oder rein informativen Verzeichnis-Treffers. */
final readonly class ExternalPhoneContactMatch {
    /** @param list<string> $sourceLabels */
    public function __construct(
        public Customer|ForeignCustomer|null $target,
        public ?string $displayName,
        public array $sourceLabels,
        public bool $ambiguous,
    ) {}
}
