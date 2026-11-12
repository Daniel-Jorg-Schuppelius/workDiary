<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IssuerIdentity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\DocumentDesign;

use App\Models\Platform\Organization;

/**
 * Ausstellerangaben für die Informationsblöcke der Belege (MVP-1609):
 * Firmenangaben, Steuerangaben und Ansprechpartner. Vorrang haben die
 * Verkäuferdaten der E-Rechnung, damit das sichtbare PDF der strukturierten
 * Rechnung nicht widerspricht; Lücken füllt das Branding der Organisation.
 */
final class IssuerIdentity {
    private const array KEYS = ['name', 'street', 'zip', 'city', 'country', 'register', 'legal_note', 'vat_id', 'tax_number', 'contact_name', 'contact_phone', 'contact_email'];

    /**
     * @param array<string, mixed>|null $frozen Am Beleg eingefrorene Angaben (Verkäufer- bzw. Aussteller-Snapshot); gewinnen je Feld.
     * @return array{name: string, street: string, zip: string, city: string, country: string, register: string, legal_note: string,
     *               vat_id: string, tax_number: string, contact_name: string, contact_phone: string, contact_email: string}
     */
    public static function for(?Organization $organization, ?array $frozen = null): array {
        $settings = $organization !== null && is_array($organization->settings) ? $organization->settings : [];
        $einvoice = is_array($settings['einvoice'] ?? null) ? $settings['einvoice'] : [];
        $branding = $organization?->brandingSettings() ?? [];
        $contact = (array) ($branding['contact'] ?? []);
        $legal = (array) ($branding['legal'] ?? []);

        $layers = [
            (array) $frozen,
            // Dieselben Schlüssel, die XRechnungGenerator::sellerDataFor() liest.
            ['name' => $einvoice['seller_name'] ?? null] + $einvoice,
            [
                'name' => $organization?->name,
                'street' => $contact['street'] ?? null,
                'zip' => $contact['postal_code'] ?? null,
                'city' => $contact['city'] ?? null,
                'country' => $contact['country'] ?? null,
                'register' => $legal['register'] ?? null,
                'legal_note' => $legal['footer_text'] ?? null,
                'vat_id' => $legal['vat_id'] ?? null,
                'tax_number' => $legal['tax_number'] ?? null,
                'contact_phone' => $contact['phone'] ?? null,
                'contact_email' => $contact['email'] ?? null,
            ],
        ];

        $issuer = [];
        foreach (self::KEYS as $key) {
            $issuer[$key] = '';
            foreach ($layers as $layer) {
                $value = is_scalar($layer[$key] ?? null) ? trim((string) $layer[$key]) : '';
                if ($value !== '') {
                    $issuer[$key] = $value;
                    break;
                }
            }
        }

        return $issuer;
    }

    /**
     * Name und vollständige Anschrift (§ 14 Abs. 4 Nr. 1 UStG).
     *
     * @param array<string, mixed> $issuer
     */
    public static function hasCompanyIdentity(array $issuer): bool {
        foreach (['name', 'street', 'zip', 'city'] as $key) {
            if (trim((string) ($issuer[$key] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Steuernummer oder USt-IdNr. (§ 14 Abs. 4 Nr. 2 UStG).
     *
     * @param array<string, mixed> $issuer
     */
    public static function hasTaxIdentity(array $issuer): bool {
        return trim((string) ($issuer['vat_id'] ?? '')) !== '' || trim((string) ($issuer['tax_number'] ?? '')) !== '';
    }
}
