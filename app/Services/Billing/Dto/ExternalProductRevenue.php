<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalProductRevenue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

/**
 * Umsatz je Artikel aus dem Buchhaltungsprogramm (MVP-1035). Mit
 * {@see $articleId} liegt die Zeile auf dem eigenen Artikelstamm, sonst mit
 * {@see $externalKey} als eigene Zeile, ohne beides als „ohne Artikelbezug“.
 */
final readonly class ExternalProductRevenue {
    public function __construct(
        public ?int $articleId,
        public ?string $externalKey,
        public ?string $number,
        public string $name,
        public ?string $category,
        public ?string $unit,
        public float $quantity,
        public float $net,
        public int $documents,
    ) {}
}
