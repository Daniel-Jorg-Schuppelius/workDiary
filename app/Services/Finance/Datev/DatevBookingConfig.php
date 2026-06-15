<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Datev;

use App\Enums\Finance\ChartOfAccounts;
use App\Models\{Customer, Organization};

/**
 * Buchhaltungskonfiguration je Organisation (Feature 045, „Priorität 2": die
 * vor dem Export fehlenden Stammdaten — Berater-/Mandantennummer, Kontenrahmen,
 * Sachkonten, Steuerschlüssel, Debitoren-Nummernkreis, Festschreibekennzeichen).
 *
 * Ablage in organizations.settings['datev'] (Settings-Ebene „global/org", siehe
 * MEMORY: drei Ablage-Ebenen) — keine vierte Mechanik. Dieses Value Object
 * liest die Gruppe robust mit dokumentierten Defaults und kapselt die
 * Debitorennummern-Vergaberegel.
 */
final class DatevBookingConfig {
    /**
     * @param array<string, string> $taxKeyMap  Steuersatz (als 2-NK-String) ⇒ DATEV-BU-Schlüssel
     */
    private function __construct(
        public readonly int $advisorNumber,
        public readonly int $clientNumber,
        public readonly ChartOfAccounts $skr,
        public readonly int $accountLength,
        public readonly string $revenueAccount,
        public readonly string $taxFreeRevenueAccount,
        public readonly int $debtorBase,
        public readonly array $taxKeyMap,
        public readonly bool $finalize,
        public readonly string $encoding,
    ) {}

    public static function forOrganization(?Organization $organization): self {
        $settings = is_array($organization?->settings) ? $organization->settings : [];
        $datev = is_array($settings['datev'] ?? null) ? $settings['datev'] : [];

        $skr = ChartOfAccounts::tryFrom((string) ($datev['skr'] ?? '')) ?? ChartOfAccounts::Skr03;

        $accountLength = (int) ($datev['account_length'] ?? 4);
        if ($accountLength < 4 || $accountLength > 8) {
            $accountLength = 4;
        }

        $revenue = trim((string) ($datev['revenue_account'] ?? ''));
        $revenueFree = trim((string) ($datev['revenue_account_tax_free'] ?? ''));

        $encoding = strtoupper(trim((string) ($datev['encoding'] ?? '')));
        if ($encoding !== 'UTF-8' && $encoding !== 'ISO-8859-1') {
            // DATEV-üblich ist ISO-8859-1 (CP1252-nah); UTF-8 nur, wenn explizit
            // gesetzt. Default daher ISO-8859-1.
            $encoding = 'ISO-8859-1';
        }

        return new self(
            advisorNumber: (int) ($datev['advisor_number'] ?? 0),
            clientNumber: (int) ($datev['client_number'] ?? 0),
            skr: $skr,
            accountLength: $accountLength,
            revenueAccount: $revenue !== '' ? $revenue : $skr->defaultRevenueAccount(),
            taxFreeRevenueAccount: $revenueFree !== '' ? $revenueFree : $skr->defaultTaxFreeRevenueAccount(),
            debtorBase: (int) ($datev['debtor_base'] ?? 10000),
            taxKeyMap: self::resolveTaxKeyMap($datev['tax_keys'] ?? null),
            finalize: self::boolish($datev['finalize'] ?? null, true),
            encoding: $encoding,
        );
    }

    /**
     * Erlöskonto je Steuersatz: 0 % / steuerfrei ⇒ eigenes Konto, sonst Standard.
     */
    public function revenueAccountFor(float $taxRate): string {
        return $taxRate <= 0.0 ? $this->taxFreeRevenueAccount : $this->revenueAccount;
    }

    /**
     * DATEV-BU-Schlüssel für einen Steuersatz (z. B. 19,00 ⇒ "3", 7,00 ⇒ "2",
     * 0,00 ⇒ "0"). Unbekannte Sätze liefern null (Preflight-Warnung).
     */
    public function taxKeyFor(float $taxRate): ?string {
        $value = $this->taxKeyMap[self::rateKey($taxRate)] ?? null;

        return $value !== null && $value !== '' ? $value : null;
    }

    /**
     * Normalisierter, garantiert nicht-numerischer Map-Schlüssel für einen
     * Steuersatz (Präfix verhindert die int-Coercion numerischer Array-
     * Schlüssel und hält den Map-Typ stabil bei array<string, string>).
     */
    private static function rateKey(float|string $rate): string {
        return 'r' . number_format((float) $rate, 2, '.', '');
    }

    /**
     * Debitorenkonto eines Kunden: explizite customers.debtor_no hat Vorrang;
     * sonst deterministische Vergaberegel (Nummernkreis-Basis + Kunden-ID als
     * Offset). Dokumentiert im Hilfe-Topic.
     */
    public function debtorAccountFor(Customer $customer): string {
        $explicit = trim((string) ($customer->debtor_no ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        return (string) ($this->debtorBase + (int) $customer->id);
    }

    /**
     * Sind die Pflicht-Stammdaten gepflegt (Berater-/Mandantennummer)?
     */
    public function hasClientNumbers(): bool {
        return $this->advisorNumber > 0 && $this->clientNumber > 0;
    }

    /**
     * Standard-Mapping 19/7/0 % ⇒ BU-Schlüssel, mit Org-Overrides gemerged.
     *
     * @return array<string, string>
     */
    private static function resolveTaxKeyMap(mixed $raw): array {
        $map = [
            self::rateKey(19.0) => '3',
            self::rateKey(7.0) => '2',
            self::rateKey(0.0) => '0',
        ];

        if (! is_array($raw)) {
            return $map;
        }

        foreach ($raw as $rate => $key) {
            $map[self::rateKey((string) $rate)] = (string) $key;
        }

        return $map;
    }

    private static function boolish(mixed $value, bool $default): bool {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
