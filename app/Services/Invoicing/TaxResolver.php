<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\{Customer, Organization, TaxRule};
use CommonToolkit\Helper\Data\Validator;

/**
 * Länderspezifische Steuerlogik der LOKALEN Fakturierung (Restpunkt 68,
 * ausgebaut in Phase 23 / MVP-239): stichtagsfähige Auflösung über die
 * versionierte Steuerregelmatrix (tax_rules; Org-Zeilen überschreiben den
 * ausgelieferten Katalog) mit EXPLIZITEM Fallback auf den statischen
 * Katalog (config/taxation.php).
 *
 *  - Inland: Org-Override (settings.invoicing.default_tax_rate), sonst
 *    Regelmatrix (Kategorie + Leistungsdatum), sonst statischer Katalog.
 *  - EU-Ausland B2B mit formal gültiger USt-IdNr.: 0 % + Reverse Charge
 *    (EN 16931 Kategorie AE) + Pflichthinweis.
 *  - EU-B2C: Verkäuferland-Satz (MVP, bewusst KEIN stiller OSS/IOSS-
 *    Automatismus — MVP-241-Abgrenzung).
 *  - Drittland: 0 % + Export-Hinweis (Kategorie G).
 * Keine Kursumrechnung — Beträge bleiben in der Belegwährung des Kunden.
 */
class TaxResolver {
    /**
     * @return array{rate: string, reverse_charge: bool, note: ?string, category: string, rule: ?array{id: int, source: ?string, valid_from: string, org_override: bool}}
     */
    public function resolve(Organization $organization, Customer $customer, ?\DateTimeInterface $onDate = null, string $category = 'services'): array {
        $onDate ??= now();

        // Kleinunternehmer § 19 UStG (Feature 066, MVP-163): kein
        // Steuerausweis — hat Vorrang vor Regelmatrix/Reverse Charge.
        $smallBusiness = (string) data_get((array) ($organization->settings ?? []), 'einvoice.small_business', '0');
        if ($smallBusiness === '1') {
            return [
                'rate' => '0.00',
                'reverse_charge' => false,
                'note' => (string) __('Keine Umsatzsteuer gemäß § 19 UStG (Kleinunternehmerregelung).'),
                'category' => 'E',
                'rule' => null,
            ];
        }

        $sellerCountry = $this->sellerCountry($organization);
        $buyerCountry = strtoupper(trim((string) ($customer->country ?? ''))) ?: $sellerCountry;

        // Inland: expliziter Org-Override vor Regelmatrix vor statischem
        // Katalog (bewusst NICHT Setting::get — das fiele auf den
        // config-Default 19.00 zurück und würde den Katalog aushebeln).
        if ($buyerCountry === $sellerCountry) {
            $override = trim((string) data_get((array) ($organization->settings ?? []), 'invoicing.default_tax_rate', ''));
            if ($override !== '') {
                return ['rate' => $override, 'reverse_charge' => false, 'note' => null, 'category' => 'S', 'rule' => null];
            }

            $rule = $this->ruleFor((int) $organization->id, $sellerCountry, $category, 'standard', $onDate);
            if ($rule !== null) {
                return [
                    'rate' => $rule->rate?->getNumericValue() ?? '0.00',
                    'reverse_charge' => false,
                    'note' => $rule->note,
                    'category' => 'S',
                    'rule' => $this->ruleMeta($rule),
                ];
            }

            // Expliziter Fallback (MVP-239): statischer Katalog.
            return ['rate' => $this->standardRate($sellerCountry), 'reverse_charge' => false, 'note' => null, 'category' => 'S', 'rule' => null];
        }

        $eu = array_map('strtoupper', (array) config('taxation.eu_countries', []));
        $vatId = str_replace(' ', '', (string) ($customer->vat_id ?? ''));

        // EU-B2B mit formal gültiger USt-IdNr. → Reverse Charge (AE).
        if (in_array($buyerCountry, $eu, true) && $vatId !== '' && Validator::isValidVatId($vatId)) {
            $rule = $this->ruleFor((int) $organization->id, $sellerCountry, $category, 'reverse_charge', $onDate);
            $note = $rule !== null && $rule->note !== null ? $rule->note : (string) config('taxation.notes.reverse_charge');

            return [
                'rate' => '0.00',
                'reverse_charge' => true,
                'note' => $note,
                'category' => 'AE',
                'rule' => $rule !== null ? $this->ruleMeta($rule) : null,
            ];
        }

        // EU-B2C (keine/ungültige USt-ID): Verkäuferland-Satz (kein OSS-Automatismus).
        if (in_array($buyerCountry, $eu, true)) {
            $rule = $this->ruleFor((int) $organization->id, $sellerCountry, $category, 'standard', $onDate);

            return [
                'rate' => $rule?->rate?->getNumericValue() ?? $this->standardRate($sellerCountry),
                'reverse_charge' => false,
                'note' => $rule?->note,
                'category' => 'S',
                'rule' => $rule !== null ? $this->ruleMeta($rule) : null,
            ];
        }

        // Drittland: nicht im Inland steuerbar (Export, Kategorie G).
        $rule = $this->ruleFor((int) $organization->id, $sellerCountry, $category, 'export', $onDate);
        $note = $rule !== null && $rule->note !== null ? $rule->note : (string) config('taxation.notes.export');

        return [
            'rate' => '0.00',
            'reverse_charge' => false,
            'note' => $note,
            'category' => 'G',
            'rule' => $rule !== null ? $this->ruleMeta($rule) : null,
        ];
    }

    /**
     * Stichtagsfähige Regel (MVP-239): Org-Zeilen vor Katalog; innerhalb
     * die jüngste zum Stichtag gültige aktive Regel. Kategorie fällt auf
     * 'services' zurück, wenn für die konkrete Kategorie nichts existiert.
     */
    public function ruleFor(int $organizationId, string $country, string $category, string $rateType, \DateTimeInterface $onDate): ?TaxRule {
        foreach ([$organizationId, null] as $owner) {
            foreach (array_unique([$category, 'services']) as $lookupCategory) {
                $rule = TaxRule::query()
                    ->where('status', 'active')
                    ->where('country', strtoupper($country))
                    ->where('category', $lookupCategory)
                    ->where('rate_type', $rateType)
                    ->whereDate('valid_from', '<=', $onDate)
                    ->where(fn($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $onDate))
                    ->when($owner === null, fn($q) => $q->whereNull('organization_id'), fn($q) => $q->where('organization_id', $owner))
                    ->orderByDesc('valid_from')
                    ->first();
                if ($rule !== null) {
                    return $rule;
                }
            }
        }

        return null;
    }

    /**
     * Überschneidungsprüfung (MVP-238/242): keine zwei aktiven Regeln für
     * denselben fachlichen Gültigkeitsbereich mit überlappendem Zeitraum.
     */
    public function assertNoOverlap(TaxRule $candidate): void {
        $overlap = TaxRule::query()
            ->where('status', 'active')
            ->whereKeyNot($candidate->id ?? 0)
            ->where('country', $candidate->country)
            ->where('category', $candidate->category)
            ->where('rate_type', $candidate->rate_type)
            ->when($candidate->region === null, fn($q) => $q->whereNull('region'), fn($q) => $q->where('region', $candidate->region))
            ->when($candidate->organization_id === null, fn($q) => $q->whereNull('organization_id'), fn($q) => $q->where('organization_id', $candidate->organization_id))
            ->where(function ($q) use ($candidate): void {
                $q->where(fn($w) => $w->whereNull('valid_to')->orWhereDate('valid_to', '>=', $candidate->valid_from));
                if ($candidate->valid_to !== null) {
                    $q->whereDate('valid_from', '<=', $candidate->valid_to);
                }
            })
            ->exists();

        if ($overlap) {
            throw new \RuntimeException((string) __('Überschneidung: für :country/:category/:type existiert bereits eine aktive Regel im Zeitraum.', [
                'country' => $candidate->country,
                'category' => $candidate->category,
                'type' => $candidate->rate_type,
            ]));
        }
    }

    public function sellerCountry(Organization $organization): string {
        $settings = (array) ($organization->settings ?? []);
        $country = strtoupper(trim((string) data_get($settings, 'einvoice.country', '')));

        return $country !== '' ? $country : 'DE';
    }

    private function standardRate(string $country): string {
        return (string) config("taxation.rates.{$country}.standard", config('taxation.rates.DE.standard', '19.00'));
    }

    /** @return array{id: int, source: ?string, valid_from: string, org_override: bool} */
    private function ruleMeta(TaxRule $rule): array {
        return [
            'id' => (int) $rule->id,
            'source' => $rule->source,
            'valid_from' => $rule->valid_from->toDateString(),
            'org_override' => $rule->organization_id !== null,
        ];
    }
}
