<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BlockPrice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

/**
 * Ergebnis der Preisfindung einer Abrechnungsposition (MVP-485): Satz plus
 * Herkunft. Die Herkunft macht in Vorschau und Übergabepaket sichtbar, warum
 * ein Preis so aussieht — und warum er im Zweifel 0,00 € ist.
 */
final class BlockPrice {
    /** Satz aus den rate-Snapshots der Zeiten (enthält alle gepflegten Sätze). */
    public const SOURCE_SNAPSHOT = 'snapshot';

    /** Stundensatz-Snapshot des primären Zeiteintrags. */
    public const SOURCE_ENTRY = 'entry';

    public const SOURCE_CUSTOMER = 'customer';

    /** Nettopreis der hinterlegten Standardleistung. */
    public const SOURCE_SERVICE = 'service';

    /** Organisations-Standarderlös (MVP-482). */
    public const SOURCE_ORG_DEFAULT = 'org_default';

    /** Nichts auflösbar — die Position wäre 0,00 €. */
    public const SOURCE_NONE = 'none';

    public function __construct(
        public readonly float $rate,
        public readonly string $source,
    ) {}

    public function isUnpriced(): bool {
        return $this->source === self::SOURCE_NONE || $this->rate <= 0.0;
    }

    /** Kurzlabel für Vorschau/Meldungen. */
    public function sourceLabel(): string {
        return match ($this->source) {
            self::SOURCE_SNAPSHOT => (string) __('finance.price_source.snapshot'),
            self::SOURCE_ENTRY => (string) __('finance.price_source.entry'),
            self::SOURCE_CUSTOMER => (string) __('finance.price_source.customer'),
            self::SOURCE_SERVICE => (string) __('finance.price_source.service'),
            self::SOURCE_ORG_DEFAULT => (string) __('finance.price_source.org_default'),
            default => (string) __('finance.price_source.none'),
        };
    }
}
