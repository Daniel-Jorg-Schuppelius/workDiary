<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningVideoProgressService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningBlockKind;
use App\Models\Attachments\Attachment;
use App\Models\Learning\{LearningEnrollment, LearningUnit, LearningVideoProgress};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pflichtanteil eines Kursvideos (Feature 149, MVP-1598).
 *
 * Der Player meldet neu gesehene Sekunden; der Server zählt sie nur so
 * schnell mit, wie die Uhr läuft, und nie über die Videodauer hinaus.
 * Gespeichert wird allein die Summe — keine Abschnitte, keine Zeitpunkte.
 */
class LearningVideoProgressService {
    /** Schnellste angerechnete Wiedergabe: zwei- bis zweieinhalbfache Geschwindigkeit samt Meldeverzug. */
    private const float MAX_RATE = 2.5;

    /** Obergrenze je Meldung — eine lange Funkpause holt der Player in Schritten nach. */
    private const int MAX_STEP_SECONDS = 60;

    /** Vorschuss der ersten Meldung, zu der es noch keine Vergleichszeit gibt. */
    private const int FIRST_STEP_SECONDS = 30;

    /** Die letzten Sekunden meldet kein Browser verlässlich. */
    private const int END_TOLERANCE_SECONDS = 2;

    /**
     * Videos der Einheit mit Pflichtanteil: Anhang → Prozent. Fremdvideos
     * (nur Adresse) fehlen hier, ihr Fortschritt ist nicht messbar.
     *
     * @return array<int, int>
     */
    public function requirementsFor(LearningUnit $unit): array {
        $required = [];
        foreach ($unit->blocks() as $block) {
            $attachmentId = (int) ($block['attachment_id'] ?? 0);
            $percent = (int) ($block['require_percent'] ?? 0);
            if (($block['type'] ?? null) === LearningBlockKind::Video->value && $attachmentId > 0 && $percent > 0) {
                $required[$attachmentId] = min(100, max($required[$attachmentId] ?? 0, $percent));
            }
        }

        if ($required === []) {
            return [];
        }

        // Nur Anhänge dieser Einheit: ein importierter Block mit fremder
        // Anhangsnummer wäre sonst eine unerfüllbare Pflicht.
        $own = $unit->attachments->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        return array_intersect_key($required, array_flip($own));
    }

    /**
     * Gesehener Anteil je Anhang für diese Einschreibung.
     *
     * @return array<int, int>
     */
    public function percentsFor(LearningEnrollment $enrollment): array {
        return LearningVideoProgress::query()
            ->withoutGlobalScopes()
            ->where('learning_enrollment_id', $enrollment->id)
            ->pluck('watched_percent', 'attachment_id')
            ->map(static fn (mixed $percent): int => (int) $percent)
            ->all();
    }

    /**
     * Offene Pflichtanteile der Einheit.
     *
     * @param  array<int, int>|null  $percents  bereits gelesene Anteile (spart die Abfrage)
     * @return list<array{attachment_id: int, required: int, percent: int}>
     */
    public function unmetFor(LearningEnrollment $enrollment, LearningUnit $unit, ?array $percents = null): array {
        $requirements = $this->requirementsFor($unit);
        if ($requirements === []) {
            return [];
        }
        $percents ??= $this->percentsFor($enrollment);

        $unmet = [];
        foreach ($requirements as $attachmentId => $required) {
            $percent = (int) ($percents[$attachmentId] ?? 0);
            if ($percent < $required) {
                $unmet[] = ['attachment_id' => $attachmentId, 'required' => $required, 'percent' => $percent];
            }
        }

        return $unmet;
    }

    /** Abschluss verweigern, solange ein Pflichtanteil fehlt. */
    public function guardWatched(LearningEnrollment $enrollment, LearningUnit $unit): void {
        $unmet = $this->unmetFor($enrollment, $unit);
        if ($unmet !== []) {
            throw ValidationException::withMessages([
                'unit' => (string) __('learning.errors.video_percent_not_reached', [
                    'percent' => $unmet[0]['percent'],
                    'required' => $unmet[0]['required'],
                ]),
            ]);
        }
    }

    /**
     * Neu gesehene Sekunden anrechnen.
     *
     * @return array{accepted: int, percent: int, required: int, satisfied: bool, unit_satisfied: bool}
     */
    public function record(LearningEnrollment $enrollment, LearningUnit $unit, Attachment $attachment, int $seconds, ?Carbon $now = null): array {
        $now ??= Carbon::now();
        $duration = (int) ($attachment->media_duration_seconds ?? 0);
        if ($duration <= 0) {
            throw ValidationException::withMessages([
                'seconds' => (string) __('learning.errors.video_duration_unknown'),
            ]);
        }

        $row = DB::transaction(function () use ($enrollment, $unit, $attachment, $seconds, $now, $duration): array {
            $progress = LearningVideoProgress::query()
                ->withoutGlobalScopes()
                ->where('learning_enrollment_id', $enrollment->id)
                ->where('attachment_id', $attachment->id)
                ->lockForUpdate()
                ->first();

            if ($progress === null) {
                $budget = self::FIRST_STEP_SECONDS;
                $progress = new LearningVideoProgress([
                    'organization_id' => $enrollment->organization_id,
                    'learning_enrollment_id' => $enrollment->id,
                    'learning_unit_id' => $unit->id,
                    'attachment_id' => $attachment->id,
                    'watched_seconds' => 0,
                    'watched_percent' => 0,
                ]);
            } else {
                $elapsed = max(0, (int) $progress->updated_at?->diffInSeconds($now, false));
                $budget = (int) min(self::MAX_STEP_SECONDS, floor($elapsed * self::MAX_RATE));
            }

            $accepted = max(0, min($seconds, $budget, $duration - $progress->watched_seconds));
            $watched = $progress->watched_seconds + $accepted;
            $percent = $watched + self::END_TOLERANCE_SECONDS >= $duration ? 100 : (int) floor($watched * 100 / $duration);

            $progress->watched_seconds = $watched;
            // Der Anteil fällt nie — auch nicht, wenn das Video später durch ein längeres ersetzt wird.
            $progress->watched_percent = max($progress->watched_percent, $percent);
            // Die Vergleichszeit rückt mit jeder Meldung vor, sonst sammelte Dauerfeuer Guthaben an.
            $progress->updated_at = $now;
            $progress->save();

            return ['accepted' => $accepted, 'percent' => $progress->watched_percent];
        });

        $required = $this->requirementsFor($unit)[(int) $attachment->id] ?? 0;

        return [
            'accepted' => $row['accepted'],
            'percent' => $row['percent'],
            'required' => $required,
            'satisfied' => $row['percent'] >= $required,
            'unit_satisfied' => $this->unmetFor($enrollment, $unit) === [],
        ];
    }
}
