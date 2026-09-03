<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExtraLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

/**
 * Rechnungsposition, die nach einem Microsoft-Produkt aussieht, aber keiner
 * fälligen Periode zugeordnet werden konnte — Kandidat für „berechnet ohne
 * laufendes Abo" oder für eine Edition, die der Abgleich nicht erkennt.
 */
final readonly class ExtraLine {
    public function __construct(
        public InvoiceLine $line,
        public float $remainingQuantity,
    ) {}
}
