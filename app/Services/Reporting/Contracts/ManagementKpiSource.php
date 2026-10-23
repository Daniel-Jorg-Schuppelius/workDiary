<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Contracts;

use App\Models\Platform\{Organization, User};
use App\Services\Reporting\Dto\ManagementKpi;
use Carbon\CarbonImmutable;

/**
 * Kennzahlbaustein der Leitungssicht (Feature 184). Jede Quelle rechnet mit
 * dem Builder ihrer Fachauswertung — die Zahl ist dort nachrechenbar — und
 * liefert nichts, wenn dem Betrachter Recht oder Modul fehlen.
 */
interface ManagementKpiSource {
    /** Bereich der Leitungssicht: finance, operations, sales, service oder quality. */
    public function group(): string;

    /** Position innerhalb des Bereichs; kleinere Werte zuerst. */
    public function order(): int;

    /** @return list<ManagementKpi> */
    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array;
}
