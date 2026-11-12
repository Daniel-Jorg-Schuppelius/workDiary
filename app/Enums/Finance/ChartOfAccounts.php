<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChartOfAccounts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Kontenrahmen für den DATEV-Buchungsstapel (Feature 045, „Rechnungswesen":
 * SKR03/SKR04 organisationsbezogen konfigurieren). Steuert das Standard-
 * Erlöskonto-Default je Organisation.
 */
enum ChartOfAccounts: string implements HasLabel {
    use HasOptions;

    case Skr03 = 'skr03';
    case Skr04 = 'skr04';

    public function label(): string {
        return (string) __('enums.finance.chart-of-accounts.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Skr03 => 'info',
            self::Skr04 => 'neutral',
        };
    }

    /**
     * Konventionelles Standard-Erlöskonto (19 %, Inland) je Kontenrahmen.
     * SKR03 ⇒ 8400, SKR04 ⇒ 4400 (nur als Default, in der Org-Konfig
     * überschreibbar).
     */
    public function defaultRevenueAccount(): string {
        return match ($this) {
            self::Skr03 => '8400',
            self::Skr04 => '4400',
        };
    }

    /**
     * Konventionelles Erlöskonto für steuerfreie/0 %-Umsätze je Kontenrahmen.
     * SKR03 ⇒ 8200, SKR04 ⇒ 4200 (Default, überschreibbar).
     */
    public function defaultTaxFreeRevenueAccount(): string {
        return match ($this) {
            self::Skr03 => '8200',
            self::Skr04 => '4200',
        };
    }

    /**
     * Gewährtes Skonto auf Ausgangsrechnungen — eine Erlösschmälerung.
     * SKR03 ⇒ 8736, SKR04 ⇒ 4736 (Default, in der Org-Konfig überschreibbar).
     *
     * Gebraucht für die Gegenbuchung beim OPOS-Ausgleich (Sicherheitsscan
     * 2026-08-23, S-38): ohne sie war der Posten ausgeglichen, das Journal
     * aber unverändert — Forderung weg, Erlös unangetastet.
     */
    public function defaultDiscountAccount(): string {
        return match ($this) {
            self::Skr03 => '8736',
            self::Skr04 => '4736',
        };
    }

    /**
     * Ausbuchung einer uneinbringlichen Forderung.
     * SKR03 ⇒ 2400, SKR04 ⇒ 6930 (Default, überschreibbar).
     *
     * Bewusst das Konto für den EINFACHEN Forderungsverlust: die Unterscheidung
     * nach Steuersatz und die Frage der Umsatzsteuerberichtigung gehören in die
     * Buchhaltung, nicht in eine Vorgabe. Wer es genauer braucht, setzt das
     * Konto je Organisation.
     */
    public function defaultWriteOffAccount(): string {
        return match ($this) {
            self::Skr03 => '2400',
            self::Skr04 => '6930',
        };
    }

    /**
     * Erhaltenes Skonto auf Eingangsrechnungen — mindert den Aufwand, nicht
     * den Erlös. SKR03 ⇒ 3736, SKR04 ⇒ 5736 (Default, überschreibbar).
     */
    public function defaultReceivedDiscountAccount(): string {
        return match ($this) {
            self::Skr03 => '3736',
            self::Skr04 => '5736',
        };
    }

    /**
     * Skontokonto des ermäßigten Satzes neben der Vorgabe (19 %): Die Konten
     * der Rahmen sind je Steuersatz getrennt. Gilt nur, solange die
     * Organisation kein eigenes Skontokonto eingestellt hat.
     *
     * @return array{granted: string, received: string}
     */
    public function reducedRateDiscountAccounts(): array {
        return match ($this) {
            self::Skr03 => ['granted' => '8731', 'received' => '3731'],
            self::Skr04 => ['granted' => '4731', 'received' => '5731'],
        };
    }

    /**
     * Ausbuchung einer Verbindlichkeit, die nicht mehr zu zahlen ist — ein
     * sonstiger Ertrag, kein Forderungsverlust. SKR03 ⇒ 2709, SKR04 ⇒ 4839
     * (Default, überschreibbar).
     */
    public function defaultPayableWriteOffAccount(): string {
        return match ($this) {
            self::Skr03 => '2709',
            self::Skr04 => '4839',
        };
    }
}
