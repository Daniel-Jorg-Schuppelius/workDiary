<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinanceMovementSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dashboard\Contracts;

use App\Models\Platform\{Organization, User};
use App\Services\Dashboard\Dto\FinanceMovementSummary;
use Carbon\CarbonImmutable;

/**
 * Erweiterungspunkt der Kachel „Finanzbewegungen“ (Feature 181, MVP-1617):
 * Kontoauszüge, Firefly III, Shop-Konten. Ein Modul meldet seine Quelle über
 * `Manifest::extensions()`, ein Plugin über `ModuleRegistry::contribute()`.
 * Die Quelle bleibt führend; die Kachel liest und verlinkt auf ihre Liste.
 */
interface FinanceMovementSource {
    public function key(): string;

    public function label(): string;

    public function icon(): string;

    /** Darf der Nutzer die Liste der Quelle öffnen (Recht, Plugin-Schalter)? Sonst fragt die Kachel nicht. */
    public function available(User $viewer): bool;

    /** Offene Zuordnungen der Quelle und ihre jüngsten Bewegungen ab dem Stichtag. */
    public function summary(Organization $organization, CarbonImmutable $since, int $latest): FinanceMovementSummary;
}
