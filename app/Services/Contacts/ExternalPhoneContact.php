<?php

declare(strict_types=1);

namespace App\Services\Contacts;

/**
 * Provider-neutraler Kontakt-Schnappschuss für den Rufnummernabgleich.
 * Es werden bewusst nur die für Matching und Anzeige nötigen Felder geführt.
 */
final readonly class ExternalPhoneContact {
    /**
     * @param  list<string>  $phones
     */
    public function __construct(
        public string $providerId,
        public string $providerLabel,
        public string $externalId,
        public ?string $name,
        public ?string $company,
        public array $phones,
    ) {}
}
