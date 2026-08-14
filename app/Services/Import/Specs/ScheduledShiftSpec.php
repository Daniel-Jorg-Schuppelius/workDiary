<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledShiftSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\{ImportValueMapping, Organization, ScheduledShift, ShiftType, User};
use App\Services\Import\{HasMappableValues, ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\Concerns\ResolvesImportUsers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * CSV-Spezifikation für den Schichtplan-Import.
 *
 * Löst den fragilen, session-basierten {@see \App\Http\Controllers\ScheduleImportController}
 * ab: feste Header-Aliase statt hartkodierter Spaltenindizes, robuste
 * Datums-/Zeit-Parsung (mehrere Formate statt `Carbon::parse`), transaktionale
 * Verarbeitung und persistierter Fehlerbericht über die MVP-049-Engine.
 *
 * Fachlicher Schlüssel zur Idempotenz: `user_email` + `date`. Der Mitarbeiter
 * wird per E-Mail aufgelöst, der Schichttyp per Name (mandantenweit, optional).
 */
class ScheduledShiftSpec extends AbstractEntitySpec implements HasMappableValues {
    use ResolvesImportUsers;

    /** @var list<string> */
    private const DATE_FORMATS = ['Y-m-d', 'd.m.Y', 'd.m.y', 'd/m/Y', 'Y/m/d', 'm/d/Y'];

    public function entity(): ImportEntity {
        return ImportEntity::ScheduledShifts;
    }

    public function columns(): array {
        return ['user_email', 'date', 'shift_type', 'start_time', 'end_time', 'note', 'status'];
    }

    public function requiredColumns(): array {
        return ['user_email', 'date'];
    }

    public function headerAliases(): array {
        return [
            'mitarbeiter' => 'user_email',
            'benutzer' => 'user_email',
            'email' => 'user_email',
            'e-mail' => 'user_email',
            'datum' => 'date',
            'schichttyp' => 'shift_type',
            'schicht' => 'shift_type',
            'beginn' => 'start_time',
            'startzeit' => 'start_time',
            'von' => 'start_time',
            'ende' => 'end_time',
            'endzeit' => 'end_time',
            'bis' => 'end_time',
            'notiz' => 'note',
            'bemerkung' => 'note',
            'status' => 'status',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'user_email' => ($v = $this->trimmedString($raw)) !== null ? mb_strtolower($v) : null,
                'date' => $this->normalizeDate($this->trimmedString($raw)),
                'start_time', 'end_time' => $this->normalizeTime($this->trimmedString($raw)),
                'status' => ($v = $this->trimmedString($raw)) !== null ? mb_strtolower($v) : null,
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

        foreach (['start_time', 'end_time'] as $f) {
            if (! empty($row[$f]) && ! preg_match('/^\d{2}:\d{2}$/', (string) $row[$f])) {
                $issues[] = $this->formatIssue($f, (string) __('import.error.format.time'));
            }
        }

        if (! empty($row['status']) && ScheduledShiftStatus::tryFrom((string) $row['status']) === null) {
            $issues[] = $this->formatIssue('status', (string) __('import.error.format.status'));
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

            $shiftTypeId = null;
            if (! empty($row['shift_type'])) {
                $shiftTypeId = ShiftType::query()
                    ->where('organization_id', $organization->id)
                    ->where('name', $row['shift_type'])
                    ->value('id');
            }

            $status = ScheduledShiftStatus::tryFrom((string) ($row['status'] ?? '')) ?? ScheduledShiftStatus::Draft;
            $actorId = Auth::id();

            $existing = ScheduledShift::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->whereDate('date', $row['date'])
                ->first();

            $payload = [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'shift_type_id' => $shiftTypeId,
                'date' => $row['date'],
                'start_time' => $row['start_time'] ?? null,
                'end_time' => $row['end_time'] ?? null,
                'note' => $row['note'] ?? null,
                'status' => $status,
                'updated_by' => $actorId,
            ];

            if ($existing !== null) {
                $existing->fill($payload)->save();

                return [ImportOutcome::Updated, null];
            }

            $payload['created_by'] = $actorId;
            ScheduledShift::create($payload);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [
                ImportOutcome::Failed,
                new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage()),
            ];
        }
    }

    private function normalizeDate(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        foreach (self::DATE_FORMATS as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!' . $format, $value);
            } catch (Throwable) {
                continue;
            }
            if ($parsed instanceof CarbonImmutable && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizeTime(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $m) === 1) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            if ($h <= 23 && $min <= 59) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }

        return $value; // ungültiges Format → validateRow markiert es
    }
}
