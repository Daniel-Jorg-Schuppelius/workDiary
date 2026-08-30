<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningXapiService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Models\Learning\{LearningEnrollment, LearningUnit, LearningXapiStatement};
use Illuminate\Support\{Carbon, Str};

/**
 * Schlanker xAPI-Endpunkt (Feature 149, MVP-743).
 *
 * **Was das ist:** eine Ablage für Statements, die ein Inhalt sendet, plus
 * die Abbildung der drei Verben, die den Fortschritt betreffen.
 *
 * **Was das nicht ist:** ein vollwertiges Learning Record Store mit
 * Statement-Abfragesprache, Voiding, Attachments und Agent-Profilen. Wer
 * das braucht, betreibt ein LRS daneben — der Endpunkt hier bewahrt auf und
 * schreibt Fortschritt fort, mehr nicht.
 *
 * Die Statements werden **roh** gespeichert; ausgewertet wird eine Kopie.
 */
class LearningXapiService {
    /** Verben, die einen Abschluss bedeuten. */
    private const COMPLETING_VERBS = [
        'http://adlnet.gov/expapi/verbs/completed',
        'http://adlnet.gov/expapi/verbs/passed',
        'https://w3id.org/xapi/dod-isd/verbs/completed',
    ];

    /** Verben, die ein Scheitern bedeuten — sie schließen nie ab. */
    private const FAILING_VERBS = [
        'http://adlnet.gov/expapi/verbs/failed',
    ];

    public function __construct(
        private readonly LearningEnrollmentService $enrollments,
    ) {}

    /**
     * Statement aufnehmen. Doppelte Zustellungen sind bei xAPI normal —
     * dieselbe `id` wird deshalb nicht zweimal gespeichert.
     *
     * @param  array<string, mixed>  $statement
     */
    public function store(LearningEnrollment $enrollment, array $statement): LearningXapiStatement {
        $statementId = $this->uuidOrNull($statement['id'] ?? null);
        $verb = $this->stringOrNull($statement['verb']['id'] ?? null);
        $objectId = $this->stringOrNull($statement['object']['id'] ?? null);

        $existing = $statementId !== null
            ? LearningXapiStatement::query()
                ->where('organization_id', $enrollment->organization_id)
                ->where('statement_id', $statementId)
                ->first()
            : null;

        if ($existing !== null) {
            return $existing;
        }

        $record = LearningXapiStatement::query()->create([
            'organization_id' => $enrollment->organization_id,
            'learning_enrollment_id' => $enrollment->id,
            'statement_id' => $statementId,
            'verb' => $verb,
            'object_id' => $objectId !== null ? mb_substr($objectId, 0, 500) : null,
            'payload' => (string) json_encode($statement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'stored_at' => Carbon::now(),
        ]);

        $this->applyProgress($enrollment, $statement, $verb);

        return $record;
    }

    /**
     * Fortschritt aus einem Statement ableiten — nur bei eindeutigen
     * Verben und nur, wenn das Ergebnis nicht auf Scheitern lautet.
     *
     * @param  array<string, mixed>  $statement
     */
    private function applyProgress(LearningEnrollment $enrollment, array $statement, ?string $verb): void {
        if ($verb === null || in_array($verb, self::FAILING_VERBS, true)) {
            return;
        }

        if (! in_array($verb, self::COMPLETING_VERBS, true)) {
            return;
        }

        // Ein `completed` mit `success: false` ist kein Nachweis.
        $success = $statement['result']['success'] ?? null;

        if ($success === false) {
            return;
        }

        $unit = $this->unitFor($enrollment);

        if ($unit === null || $enrollment->status->isFinal()) {
            return;
        }

        $this->enrollments->completeUnit($enrollment, $unit);
    }

    /**
     * Welche Einheit meint das Statement?
     *
     * Ein xAPI-Statement nennt seine Aktivität als URI, nicht unsere
     * Lerneinheit. Zugeordnet wird deshalb nur, wenn es **eindeutig** ist:
     * genau eine SCORM-Einheit im Kurs, sonst genau eine Einheit überhaupt.
     * Bei mehreren wäre jede Wahl geraten — dann bleibt das Statement
     * archiviert, ohne Fortschritt zu setzen.
     */
    private function unitFor(LearningEnrollment $enrollment): ?LearningUnit {
        $course = $enrollment->course;

        if ($course === null) {
            return null;
        }

        $scorm = $course->units()->where('kind', LearningUnitKind::Scorm->value)->get();

        if ($scorm->count() === 1) {
            return $scorm->first();
        }

        $all = $course->units()->get();

        return $all->count() === 1 ? $all->first() : null;
    }

    private function stringOrNull(mixed $value): ?string {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function uuidOrNull(mixed $value): ?string {
        $string = $this->stringOrNull($value);

        return $string !== null && Str::isUuid($string) ? $string : null;
    }
}
