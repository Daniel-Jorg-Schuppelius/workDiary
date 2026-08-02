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

/**
 * Aufgelöste Standardleistung (MVP-486): Artikelbezug, Bezeichnung, Einheit,
 * Standardtext und — als Rückfall für die Preisfindung — der Nettopreis.
 * Herkunft bleibt sichtbar, damit die Vorschau erklären kann, woher eine
 * Position ihre Werte hat.
 */
final class ResolvedService {
    public const SOURCE_PROJECT_RULE = 'project_rule';

    public const SOURCE_ORGANIZATION = 'organization';

    public function __construct(
        public readonly ?string $articleId,
        public readonly ?string $name,
        public readonly ?string $unitName,
        public readonly ?float $netPrice,
        public readonly ?float $vatRate,
        public readonly ?string $standardText,
        public readonly string $itemType,
        public readonly string $source,
    ) {}

    /**
     * Stunden-Leistung? Nur solche werden automatisch gegen erfasste Zeiten
     * gerechnet — bei einer Pauschale wäre „Menge = Stunden" schlicht falsch.
     */
    public function isHourly(): bool {
        $unit = mb_strtolower(trim((string) $this->unitName));
        if ($unit === '') {
            // Ohne Einheit bleibt es bei der bisherigen Stundenabrechnung.
            return true;
        }

        foreach (['stunde', 'std', 'hour', 'hr', 'h', 'ora', 'heure', 'hora'] as $needle) {
            if ($unit === $needle || str_starts_with($unit, $needle)) {
                return true;
            }
        }

        return false;
    }
}
