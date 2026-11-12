<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullQualificationTrainingSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule\Contracts;

use Illuminate\Support\Collection;

/** Ohne Lernplattform: keine laufenden Kurse, keine Vorschläge — die Ampel kennt nur erfüllt, läuft ab, fehlt. */
final class NullQualificationTrainingSource implements QualificationTrainingSource {
    public function openEnrollments(Collection $users, array $qualificationIds): array {
        return [];
    }

    public function suggestedCourses(array $qualificationIds): array {
        return [];
    }
}
