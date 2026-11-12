<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationTrainingSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule\Contracts;

use App\Models\Platform\User;
use Illuminate\Support\Collection;

/**
 * Einsatz-Ampel (Feature 149): laufende Kurse und Kursvorschläge zu geforderten
 * Qualifikationen, gebunden von der Lernplattform. Die Ampel informiert, gesperrt
 * wird weiter nur über den Qualifikations-Guard.
 */
interface QualificationTrainingSource {
    /**
     * Qualifikationen, für die eine Person einen zugewiesenen oder begonnenen Kurs hat.
     *
     * @param  Collection<int, User>  $users
     * @param  list<int>  $qualificationIds
     * @return array<int, list<int>> Person → Qualifikationen
     */
    public function openEnrollments(Collection $users, array $qualificationIds): array;

    /**
     * Freigegebener Kurs, der die Qualifikation vermittelt.
     *
     * @param  list<int>  $qualificationIds
     * @return array<int, string> Qualifikation → Kurstitel
     */
    public function suggestedCourses(array $qualificationIds): array;
}
