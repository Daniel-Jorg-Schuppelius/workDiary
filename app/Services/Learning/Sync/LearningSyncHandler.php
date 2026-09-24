<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningSyncHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\Sync;

use App\Models\Learning\{LearningEnrollment, LearningUnit};
use App\Models\Platform\User;
use App\Services\Learning\LearningEnrollmentService;
use App\Services\Sync\Contracts\SyncCommandHandler;
use App\Support\Sqid;
use RuntimeException;

/** Lerneinheit offline abhaken (Feature 149, MVP-748) — nur Einheiten ohne Online-Pflicht. */
final class LearningSyncHandler implements SyncCommandHandler {
    /** @return list<string> */
    public function types(): array {
        return ['learning.unit-complete'];
    }

    public function handle(User $user, string $type, array $payload): string {
        return match ($type) {
            'learning.unit-complete' => $this->learningUnitComplete($user, $payload),
            default => throw new RuntimeException('Unbekannter Sync-Befehlstyp: ' . $type),
        };
    }

    /**
     * Lerneinheit offline abhaken (Feature 149, MVP-748).
     *
     * **Die Online-Pflicht wird hier durchgesetzt**, nicht nur im Browser:
     * Prüfungen und Aufgaben dürfen nicht offline abgeschlossen werden. Eine
     * offline erzeugte Prüfungsakte wäre nicht manipulationssicher, und der
     * Nachweis hinge an ihr.
     *
     * Die Einschreibung muss der Person selbst gehören — die Outbox eines
     * Geräts darf niemanden sonst betreffen.
     *
     * @param  array<string, mixed>  $payload
     */
    private function learningUnitComplete(User $user, array $payload): string {
        $enrollmentId = Sqid::decodeOrNumeric(LearningEnrollment::class, $payload['enrollment'] ?? null);
        $unitId = Sqid::decodeOrNumeric(LearningUnit::class, $payload['unit'] ?? null);

        $enrollment = $enrollmentId !== null ? LearningEnrollment::query()->find($enrollmentId) : null;
        $unit = $unitId !== null ? LearningUnit::query()->find($unitId) : null;

        if ($enrollment === null || $unit === null) {
            throw new RuntimeException((string) __('learning.errors.sync_unknown_target'));
        }

        if ($enrollment->user_id !== $user->id) {
            throw new RuntimeException((string) __('learning.errors.sync_foreign_enrollment'));
        }

        if ($unit->kind->requiresOnline()) {
            throw new RuntimeException((string) __('learning.errors.sync_requires_online'));
        }

        // Kurspaket, Termin, Prüfung und Abgabe melden ihr Ergebnis selbst — ein
        // selbst gebauter Sync-Befehl darf sie genauso wenig abhaken wie der Knopf.
        if ($unit->reportsOwnResult()) {
            throw new RuntimeException((string) __('learning.errors.sync_reports_own_result'));
        }

        $progress = app(LearningEnrollmentService::class)->completeUnit($enrollment, $unit);

        return 'learning_unit_progress:' . $progress->id;
    }
}
