<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningScormService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{LearningEnrollment, LearningScormPackage, LearningScormState, LearningUnit};
use App\Models\User;
use App\Services\Learning\Scorm\{ScormManifest, ScormPackageException, ScormPackageExtractor};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{DB, File};
use Illuminate\Validation\ValidationException;

/**
 * SCORM-Pakete importieren und ihren Laufzeitzustand führen (Feature 149,
 * MVP-743) — einzige Schreibstelle.
 *
 * Die Zuordnung der Rohwerte auf unseren Fortschritt ist der heikle Teil:
 * SCORM 1.2 kennt `cmi.core.lesson_status` mit `passed|completed|failed|
 * incomplete|browsed|not attempted`; SCORM 2004 trennt in
 * `cmi.completion_status` (`completed|incomplete|not attempted|unknown`)
 * und `cmi.success_status` (`passed|failed|unknown`).
 *
 * **Abgeschlossen ist nicht bestanden.** Ein Inhalt, der `completed` meldet
 * und gleichzeitig `failed`, hat die Einheit nicht erfüllt — sonst würde
 * ein durchgefallener Test als Nachweis durchgehen.
 */
class LearningScormService {
    public function __construct(
        private readonly ScormPackageExtractor $extractor,
        private readonly LearningEnrollmentService $enrollments,
    ) {}

    /** Paket an einer Lerneinheit hinterlegen; ersetzt ein vorhandenes. */
    public function import(LearningUnit $unit, string $zipPath, ?User $actor = null): LearningScormPackage {
        $relative = 'learning/scorm/' . $unit->organization_id . '/' . Str::lower(Str::random(16));
        $absolute = storage_path('app/' . $relative);

        try {
            $result = $this->extractor->extract($zipPath, $absolute);
            $manifest = ScormManifest::fromXml($result['manifest']);
        } catch (ScormPackageException $e) {
            // Der Parser bleibt sprachneutral — übersetzt wird hier.
            throw ValidationException::withMessages([
                'package' => (string) __('learning.errors.scorm.' . $e->reason),
            ]);
        }

        if ($manifest->launchHref === null) {
            throw ValidationException::withMessages([
                'package' => (string) __('learning.errors.scorm.without_launch'),
            ]);
        }

        // Ein Ersatzpaket löst das alte ab — der Zustand der Lernenden bleibt
        // an der Einheit, nicht am Paket.
        $obsolete = LearningScormPackage::query()
            ->where('learning_unit_id', $unit->id)
            ->pluck('storage_path')
            ->all();

        $package = DB::transaction(function () use ($unit, $manifest, $result, $relative, $actor): LearningScormPackage {
            LearningScormPackage::query()->where('learning_unit_id', $unit->id)->delete();

            return LearningScormPackage::query()->create([
                'organization_id' => $unit->organization_id,
                'learning_unit_id' => $unit->id,
                'title' => $manifest->title !== '' ? $manifest->title : $unit->title,
                'version' => $manifest->version,
                'storage_path' => $relative,
                'launch_href' => $manifest->launchHref,
                'manifest_hash' => (string) CryptoHelper::hash($result['manifest']),
                'file_count' => $result['files'],
                'size_bytes' => $result['bytes'],
                'uploaded_by_user_id' => $actor?->id,
            ]);
        });

        // Erst nach dem Commit: die Dateien des abgelösten Pakets braucht
        // niemand mehr, ein Rollback hätte sie aber noch gebraucht.
        foreach ($obsolete as $path) {
            if (is_string($path) && $path !== '' && $path !== $relative) {
                File::deleteDirectory(storage_path('app/' . $path));
            }
        }

        return $package;
    }

    /** Zustand einer Person zu einem Paket (legt ihn bei Bedarf an). */
    public function stateFor(LearningScormPackage $package, LearningEnrollment $enrollment): LearningScormState {
        return LearningScormState::query()->firstOrCreate(
            [
                'learning_scorm_package_id' => $package->id,
                'learning_enrollment_id' => $enrollment->id,
            ],
            [
                'organization_id' => $package->organization_id,
                'session_seconds' => 0,
            ]
        );
    }

    /**
     * Meldung des Inhalts übernehmen (`LMSCommit`/`Commit`).
     *
     * @param  array<string, mixed>  $values  Rohwerte des Datenmodells
     */
    public function commit(LearningScormPackage $package, LearningEnrollment $enrollment, array $values): LearningScormState {
        $state = $this->stateFor($package, $enrollment);

        $lesson = $this->stringOrNull($values['lesson_status'] ?? null);
        $success = $this->stringOrNull($values['success_status'] ?? null);
        $scaled = $values['score_scaled'] ?? null;

        $state->fill([
            'lesson_status' => $lesson ?? $state->lesson_status,
            'success_status' => $success ?? $state->success_status,
            'score_scaled' => is_numeric($scaled) ? (string) $scaled : $state->score_scaled,
            // suspend_data und location gehören dem Inhalt — unverändert übernehmen.
            'suspend_data' => array_key_exists('suspend_data', $values)
                ? $this->stringOrNull($values['suspend_data'])
                : $state->suspend_data,
            'location' => array_key_exists('location', $values)
                ? $this->stringOrNull($values['location'])
                : $state->location,
            'session_seconds' => $state->session_seconds + max(0, (int) ($values['session_seconds'] ?? 0)),
            'last_commit_at' => Carbon::now(),
        ])->save();

        if ($this->isPassed($package, $state->refresh())) {
            $unit = $package->unit;

            if ($unit !== null && ! $enrollment->status->isFinal()) {
                $this->enrollments->completeUnit($enrollment, $unit);
            }
        }

        return $state;
    }

    /**
     * Hat der Inhalt die Einheit erfüllt?
     *
     * **Abgeschlossen ist nicht bestanden**: meldet ein SCO `completed` und
     * zugleich `failed`, zählt das nicht als Nachweis.
     */
    public function isPassed(LearningScormPackage $package, LearningScormState $state): bool {
        $success = strtolower((string) $state->success_status);

        if ($success === 'failed') {
            return false;
        }

        $lesson = strtolower((string) $state->lesson_status);

        if ($package->isScorm2004()) {
            // 2004: Abschluss aus completion_status, Bewertung aus success_status.
            return $lesson === 'completed' && $success !== 'failed';
        }

        // 1.2: ein Wert für beides.
        return in_array($lesson, ['passed', 'completed'], true);
    }

    private function stringOrNull(mixed $value): ?string {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
