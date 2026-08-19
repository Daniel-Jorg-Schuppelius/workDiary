<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\Organization;

/** Erweiterungspunkt für externe Kontaktverzeichnisse mit Rufnummern. */
interface ExternalPhoneContactSource {
    public function id(): string;

    public function label(): string;

    public function isAvailable(Organization $organization): bool;

    /** @return iterable<ExternalPhoneContact> */
    public function contacts(Organization $organization): iterable;
}
