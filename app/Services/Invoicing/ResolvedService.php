<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvedService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Support\Locales;
use ERechnungToolkit\Enums\UnitCode;

/**
 * Aufgelöste Standardleistung (MVP-486): Artikelbezug, Bezeichnung, Einheit,
 * Standardtext und — als Rückfall für die Preisfindung — der Nettopreis.
 * Der Artikelbezug ist ein Katalogschlüssel (`art:12`, `lex:34`, MVP-1026);
 * die Übersetzung in eine Artikel-ID des Zielsystems macht dessen Plugin.
 * Herkunft bleibt sichtbar, damit die Vorschau erklären kann, woher eine
 * Position ihre Werte hat.
 */
final class ResolvedService {
    public const string SOURCE_PROJECT_RULE = 'project_rule';

    public const string SOURCE_ORGANIZATION = 'organization';

    public function __construct(
        public readonly ?string $articleRef,
        public readonly ?string $name,
        public readonly ?string $unitName,
        public readonly ?float $netPrice,
        public readonly ?float $vatRate,
        public readonly ?string $standardText,
        public readonly string $itemType,
        public readonly string $source,
        /**
         * Preis ausdrücklich an der Projektregel gepflegt (nicht aus dem
         * Artikel ergänzt)? Dann schlägt er den allgemeinen Stundensatz —
         * jemand hat ihn für dieses Projekt bewusst festgelegt.
         */
        public readonly bool $priceIsExplicit = false,
    ) {}

    /**
     * Nach Dauer abrechnen? Nur die Einheit „Pauschale“ ergibt je Auftrag eine
     * Position mit Menge 1 (MVP-1678): UN/ECE `LS` laut erechnung-toolkit oder
     * die Pauschal-Einheit der App in einer ihrer Sprachen. Alle anderen
     * Einheiten (leer, Stunde, Stk, Tag, km, Freitext) rechnen nach Dauer.
     */
    public function isHourly(): bool {
        $unit = trim((string) $this->unitName);
        if ($unit === '') {
            return true;
        }
        if (UnitCode::fromText($unit) === UnitCode::LUMP_SUM) {
            return false;
        }
        $normalized = mb_strtolower(rtrim($unit, '.'));
        foreach (Locales::codes() as $locale) {
            if ($normalized === mb_strtolower(rtrim((string) trans('invoicing.unit_flat', [], $locale), '.'))) {
                return false;
            }
        }

        return true;
    }
}
