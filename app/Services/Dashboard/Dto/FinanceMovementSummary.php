<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinanceMovementSummary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dashboard\Dto;

/**
 * Stand einer Quelle der Kachel „Finanzbewegungen“. `$unassigned` zählt alle
 * Bewegungen ohne Zuordnung — genau die, die `$unassignedUrl` in der Liste der
 * Quelle zeigt; null, wenn die Quelle nichts zuordnet (Shop-Konto).
 */
final readonly class FinanceMovementSummary {
    /** @param  list<FinanceMovement>  $latest  die jüngsten Bewegungen ab dem Stichtag */
    public function __construct(
        public ?int $unassigned,
        public string $url,
        public ?string $unassignedUrl,
        public array $latest,
    ) {}
}
