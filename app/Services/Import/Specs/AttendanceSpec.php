<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\{Attendance, ImportValueMapping, Organization, User};
use App\Services\Import\{HasMappableValues, ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\Concerns\{BindsTimeImportReference, ParsesLocalDateTime, ResolvesImportUsers};
use App\Services\TimeApproval\DayCloseService;
use Throwable;

/**
 * Import-Spezifikation für Stempelungen (MVP-438).
 *
 * Erzeugt {@see Attendance}-Datensätze (Kommen/Gehen-Intervalle) aus CSV/XLSX/
 * iCal über die MVP-049-Engine. Abusekritisch, deshalb streng
 * (`attendance.import`) und mit **Sperr-Schutz**: Tage mit geschlossenem
 * Tagesabschluss oder freigegebener/gesperrter Monatsfreigabe werden nicht
 * überschrieben, sondern als Fehlerzeile gemeldet (GoBD: kein stilles
 * Überschreiben geprüfter Zeiträume). Jede Zeile trägt `source=import`, bleibt
 * also von echter Terminal-Erfassung unterscheidbar.
 */
class AttendanceSpec extends AbstractEntitySpec implements HasMappableValues {
    use BindsTimeImportReference;
    use ParsesLocalDateTime;
    use ResolvesImportUsers;

    private const EXTERNAL_TYPE = 'attendance';

    public function __construct(private readonly DayCloseService $dayClose) {}

    public function entity(): ImportEntity {
        return ImportEntity::Attendances;
    }

    public function columns(): array {
        return ['user_email', 'date', 'start_time', 'end_time', 'break_minutes', 'note', 'external_id'];
    }

    public function requiredColumns(): array {
        return ['user_email', 'date', 'start_time'];
    }

    public function headerAliases(): array {
        return [
            'mitarbeiter' => 'user_email',
            'benutzer' => 'user_email',
            'email' => 'user_email',
            'e-mail' => 'user_email',
            'datum' => 'date',
            'beginn' => 'start_time',
            'kommen' => 'start_time',
            'startzeit' => 'start_time',
            'von' => 'start_time',
            'ende' => 'end_time',
            'gehen' => 'end_time',
            'endzeit' => 'end_time',
            'bis' => 'end_time',
            'pause' => 'break_minutes',
            'pausenminuten' => 'break_minutes',
            'notiz' => 'note',
            'bemerkung' => 'note',
            'fremd-id' => 'external_id',
            'fremdschluessel' => 'external_id',
            'uid' => 'external_id',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'user_email' => ($v = $this->trimmedString($raw)) !== null ? mb_strtolower($v) : null,
                'date' => $this->normalizeImportDate($this->trimmedString($raw)),
                'start_time', 'end_time' => $this->normalizeImportTime($this->trimmedString($raw)),
                default => $this->trimmedString($raw),
            };
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];

        if (($row['user_email'] ?? null) === null) {
            $issues[] = $this->requiredIssue('user_email');
        } elseif (! filter_var($row['user_email'], FILTER_VALIDATE_EMAIL)) {
            $issues[] = $this->formatIssue('user_email', (string) __('import.error.format.email'));
        }

        if (($row['date'] ?? null) === null) {
            $issues[] = $this->requiredIssue('date');
        }

        if (($row['start_time'] ?? null) === null) {
            $issues[] = $this->requiredIssue('start_time');
        }

        foreach (['start_time', 'end_time'] as $f) {
            if (! empty($row[$f]) && ! preg_match('/^\d{2}:\d{2}$/', (string) $row[$f])) {
                $issues[] = $this->formatIssue($f, (string) __('import.error.format.time'));
            }
        }

        if (! empty($row['break_minutes']) && ! ctype_digit((string) $row['break_minutes'])) {
            $issues[] = $this->formatIssue('break_minutes', (string) __('import.error.format.default', ['field' => 'break_minutes', 'reason' => 'Minuten']));
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            // Konto-Treffer (case-insensitiv) oder Benutzer-Mapping; ignorierte
            // Adressen überspringen die Zeile statt zu scheitern.
            $user = $this->resolveImportUser($organization, (string) $row['user_email']);
            if ($user === ImportValueMapping::KIND_IGNORE) {
                return [ImportOutcome::Skipped, null];
            }

            if (! $user instanceof User) {
                return [
                    ImportOutcome::Failed,
                    new ValidationIssue(
                        ImportErrorCode::FkMissing,
                        'user_email',
                        (string) __('import.error.fkMissing.user', ['value' => (string) $row['user_email']]),
                    ),
                ];
            }

            $date = (string) $row['date'];

            // GoBD-Sperr-Schutz: kein Überschreiben geprüfter Zeiträume.
            if ($this->periodLocked($user, $date)) {
                return [
                    ImportOutcome::Failed,
                    new ValidationIssue(
                        ImportErrorCode::PeriodLocked,
                        'date',
                        (string) __('import.error.periodLocked.attendance', ['date' => $date]),
                    ),
                ];
            }

            $tz = $this->orgTimezone($organization);
            $startedAt = $this->localToUtc($date, (string) $row['start_time'], $tz);

            $endedAt = null;
            if (! empty($row['end_time'])) {
                $endedAt = $this->localToUtc($date, (string) $row['end_time'], $tz);
                if ($endedAt->lessThanOrEqualTo($startedAt)) {
                    // Über Mitternacht (z. B. 22:00–06:00) → Endzeit am Folgetag.
                    $endedAt = $endedAt->addDay();
                }
            }

            $key = $this->importKey(
                is_string($row['external_id'] ?? null) ? $row['external_id'] : null,
                $user->id . '|' . $date . '|' . $row['start_time'],
            );

            $payload = [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'date' => $date,
                'break_minutes_manual' => ! empty($row['break_minutes']) ? (int) $row['break_minutes'] : 0,
                'source' => AttendanceSource::Import,
                'note' => $row['note'] ?? null,
            ];

            $existing = $this->findImported($organization, Attendance::class, self::EXTERNAL_TYPE, $key);
            if ($existing instanceof Attendance) {
                $existing->fill($payload)->save();

                return [ImportOutcome::Updated, null];
            }

            $attendance = Attendance::create($payload);
            $this->bindImported($organization, $attendance, self::EXTERNAL_TYPE, $key);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [
                ImportOutcome::Failed,
                new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage()),
            ];
        }
    }

    /**
     * Sperr-Prüfung über die reale {@see DayCloseService}-Logik (Tagesabschluss
     * + Monatsfreigabe) mittels einer transienten Anwesenheit.
     */
    private function periodLocked(User $user, string $date): bool {
        $probe = new Attendance(['user_id' => $user->id, 'date' => $date]);
        $probe->setRelation('user', $user);

        return $this->dayClose->attendanceEditLocked($probe);
    }
}
