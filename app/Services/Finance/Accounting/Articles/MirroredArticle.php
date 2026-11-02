<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirroredArticle.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Articles;

/**
 * Ein Artikel, wie eine Katalogquelle ihn aus dem Buchhaltungsprogramm liest
 * (Feature 185, gap-09). Preis netto als Dezimalzeichenkette; `number` ist
 * die Kennung, die Belegpositionen führen, wenn sie nicht die Fremd-ID ist.
 */
final readonly class MirroredArticle {
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $number = null,
        public ?string $description = null,
        public ?string $unitName = null,
        public ?string $netUnitPrice = null,
        public string $currency = 'EUR',
        public ?string $vatRate = null,
        /** Positionsart für Belege: `service` oder `material`. */
        public string $kind = 'service',
        public bool $active = true,
    ) {}
}
