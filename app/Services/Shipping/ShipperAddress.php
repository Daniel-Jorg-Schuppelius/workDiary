<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipperAddress.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Models\Organization;
use RuntimeException;

/**
 * Absenderadresse eines Versandauftrags (Feature 059, MVP-128). UPS und FedEx
 * verlangen — anders als DHL (Absenderprofil im GK-Konto) — den Shipper-Block
 * im API-Request. Quelle sind die vorhandenen Verkäufer-Stammdaten der
 * Organisation (`organizations.settings['einvoice']`, gepflegt für die
 * E-Rechnung; vgl. XRechnungGenerator::sellerData) — bewusst keine neue
 * Ablage-Mechanik.
 */
final class ShipperAddress {
    public function __construct(
        public readonly string $name,
        public readonly string $street,
        public readonly string $zip,
        public readonly string $city,
        public readonly string $country,
    ) {}

    /**
     * Liest die Absenderadresse aus den Organisations-Stammdaten.
     *
     * @throws RuntimeException wenn Straße/PLZ/Ort nicht gepflegt sind
     */
    public static function fromOrganization(Organization $organization): self {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $einvoice = is_array($settings['einvoice'] ?? null) ? $settings['einvoice'] : [];

        $get = static fn(string $key): string => trim((string) ($einvoice[$key] ?? ''));

        $name = $get('seller_name') !== '' ? $get('seller_name') : trim((string) $organization->name);
        $street = $get('street');
        $zip = $get('zip');
        $city = $get('city');

        if ($street === '' || $zip === '' || $city === '') {
            throw new RuntimeException(
                'Shipper address incomplete: maintain the seller master data (street/zip/city) of the organization.',
            );
        }

        return new self(
            $name,
            $street,
            $zip,
            $city,
            strtoupper($get('country') !== '' ? $get('country') : 'DE'),
        );
    }
}
