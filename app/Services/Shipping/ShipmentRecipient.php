<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentRecipient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

/**
 * Empfängeradresse eines Versandauftrags (Feature 059, MVP-128) — providerneutral.
 */
final class ShipmentRecipient {
    public function __construct(
        public readonly string $name,
        public readonly string $street,
        public readonly string $zip,
        public readonly string $city,
        public readonly string $country,
        public readonly ?string $contactName = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
    ) {}

    /**
     * Adress-Schnappschuss für die Ablage am Versandauftrag (Nachweis).
     *
     * @return array<string, string|null>
     */
    public function toArray(): array {
        return [
            'name' => $this->name,
            'street' => $this->street,
            'zip' => $this->zip,
            'city' => $this->city,
            'country' => $this->country,
            'contact_name' => $this->contactName,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
