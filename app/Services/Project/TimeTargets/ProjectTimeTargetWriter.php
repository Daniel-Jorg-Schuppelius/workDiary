<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeTargetWriter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\TimeTargets;

use App\Models\Platform\User;
use App\Models\Project\Project;
use Illuminate\Support\Facades\DB;

/**
 * Ersetzt den Sollzeiten-Satz eines Projekts in einer Transaktion (Feature 194):
 * Projektvorgabe und Personenzeilen je Wochentag, Ausnahmen je Datum, eine
 * Gültigkeit für die Wochentagszeilen.
 */
final class ProjectTimeTargetWriter {
    /**
     * @param  array<int, int|null>  $default  Wochentag 1–7 => Minuten (null = keine Vorgabe)
     * @param  array<int, array<int, int|null>>  $members  user_id => [Wochentag => Minuten]; null erbt die Vorgabe
     * @param  list<array{user_id: int|null, date: string, minutes: int}>  $exceptions
     */
    public function sync(Project $project, array $default, array $members, array $exceptions, ?string $validFrom, ?string $validUntil, User $actor): void {
        $rows = [];
        foreach ($default as $weekday => $minutes) {
            if ($minutes !== null) {
                $rows[] = ['user_id' => null, 'weekday' => (int) $weekday, 'specific_date' => null, 'target_minutes' => $minutes];
            }
        }
        foreach ($members as $userId => $week) {
            foreach ($week as $weekday => $minutes) {
                if ($minutes !== null) {
                    $rows[] = ['user_id' => (int) $userId, 'weekday' => (int) $weekday, 'specific_date' => null, 'target_minutes' => $minutes];
                }
            }
        }
        foreach ($exceptions as $exception) {
            $rows[] = ['user_id' => $exception['user_id'], 'weekday' => null, 'specific_date' => $exception['date'], 'target_minutes' => $exception['minutes']];
        }

        DB::transaction(function () use ($project, $rows, $validFrom, $validUntil, $actor): void {
            $project->timeTargets()->delete();
            foreach ($rows as $row) {
                $project->timeTargets()->create($row + [
                    'organization_id' => $project->organization_id,
                    'valid_from' => $row['specific_date'] === null ? $validFrom : null,
                    'valid_until' => $row['specific_date'] === null ? $validUntil : null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }
        });
    }
}
