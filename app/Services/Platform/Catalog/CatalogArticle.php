<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogArticle.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Platform\Catalog;

use CommonToolkit\ValueObjects\{Money, Percentage};

/**
 * Anbieterneutrale Sicht auf einen Artikel aus einer Katalogquelle
 * (Phase 125, MVP-1025): Artikelstamm, Lexoffice oder ein weiteres Plugin.
 * Gespeichert wird nur {@see $key} (`<quelle>:<id>`); Formulare tragen
 * {@see $formKey} mit dem Sqid der Quelle.
 */
final readonly class CatalogArticle {
    public function __construct(
        public string $key,
        public string $formKey,
        public string $source,
        public string $sourceLabel,
        public int $id,
        public string $name,
        public ?string $number,
        public ?string $unitName,
        public ?Money $netPrice,
        public bool $active,
        public ?string $description = null,
        /** Nur gesetzt, wenn die Quelle den Steuersatz am Artikel führt. */
        public ?Percentage $vatRate = null,
        /** Positionsart für Belege: `service` oder `material`. */
        public string $itemType = 'service',
    ) {}

    public function label(): string {
        return ($this->number !== null && $this->number !== '' ? $this->number . ' · ' : '') . $this->name;
    }
}
