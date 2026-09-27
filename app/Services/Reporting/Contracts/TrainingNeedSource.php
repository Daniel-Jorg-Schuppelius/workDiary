<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingNeedSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Contracts;

use App\Models\Platform\Organization;

/** Schulungsbedarf der Organisation (MVP-926), gebunden von der Lernplattform. */
interface TrainingNeedSource {
    /**
     * Je Kompetenz: Anzahl Personen mit Lücke, mittlere Lücke in Stufen, passende freigegebene Kurse.
     *
     * @return list<array{competency: string, people: int, average_gap: float, courses: list<string>}>
     */
    public function trainingNeeds(Organization $organization): array;
}
