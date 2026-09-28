<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityActivitySpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sustainability\Import;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Models\Sustainability\{SustainabilityActivityRecord, SustainabilitySite};
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\AbstractEntitySpec;
use App\Services\Import\Specs\Concerns\ParsesLocalDateTime;
use App\Support\Query\DateRange;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * ESG-Verbräuche per Datei (MVP-973): Aktivitätsdaten wie in der
 * Einzelerfassung, Bezug auf Standort (Name oder Kürzel) oder Kunde
 * (Kundennummer). Die Datenqualität ist Pflicht, damit kein Schätzwert als
 * Messwert durchrutscht; gleicher Code, Bezug und Zeitraum gelten als Dublette.
 */
class SustainabilityActivitySpec extends AbstractEntitySpec {
    use ParsesLocalDateTime;

    private const QUALITY_ALIASES = [
        'gemessen' => 'measured', 'messung' => 'measured', 'berechnet' => 'calculated', 'geschätzt' => 'estimated', 'geschaetzt' => 'estimated', 'schätzung' => 'estimated',
    ];

    private const DEFAULT_UNITS = [
        'kwh' => 'kWh', 'l' => 'l', 'kg' => 'kg', 'm3' => 'm³', 'eur' => 'EUR', 'car' => 'km', 'truck' => 'km',
    ];

    public function entity(): ImportEntity {
        return ImportEntity::SustainabilityActivities;
    }

    public function columns(): array {
        return ['activity_code', 'amount', 'unit', 'period_start', 'period_end', 'data_quality', 'site', 'customer', 'subject_label', 'source_note'];
    }

    public function requiredColumns(): array {
        return ['activity_code', 'amount', 'period_start', 'period_end', 'data_quality'];
    }

    public function headerAliases(): array {
        return [
            'aktivität' => 'activity_code', 'aktivitaet' => 'activity_code', 'art' => 'activity_code', 'code' => 'activity_code', 'verbrauchsart' => 'activity_code',
            'menge' => 'amount', 'verbrauch' => 'amount', 'wert' => 'amount', 'einheit' => 'unit',
            'von' => 'period_start', 'beginn' => 'period_start', 'zeitraum_von' => 'period_start', 'bis' => 'period_end', 'ende' => 'period_end', 'zeitraum_bis' => 'period_end',
            'qualität' => 'data_quality', 'qualitaet' => 'data_quality', 'datenqualität' => 'data_quality', 'datenqualitaet' => 'data_quality',
            'standort' => 'site', 'kunde' => 'customer', 'kundennummer' => 'customer', 'bezeichnung' => 'subject_label', 'quelle' => 'source_note', 'notiz' => 'source_note',
        ];
    }

    public function normalize(array $row): array {
        $code = $this->activityCode($this->trimmedString($row['activity_code'] ?? null));

        return [
            'activity_code' => $code ?? $this->trimmedString($row['activity_code'] ?? null),
            'known_code' => $code !== null,
            'amount' => $this->decimal($this->trimmedString($row['amount'] ?? null)),
            'unit' => $this->trimmedString($row['unit'] ?? null) ?? ($code !== null ? self::DEFAULT_UNITS[substr($code, (int) strrpos($code, '_') + 1)] ?? null : null),
            'period_start' => $this->normalizeImportDate($this->trimmedString($row['period_start'] ?? null)),
            'period_end' => $this->normalizeImportDate($this->trimmedString($row['period_end'] ?? null)),
            'data_quality' => $this->quality($this->trimmedString($row['data_quality'] ?? null)),
            'site' => $this->trimmedString($row['site'] ?? null),
            'customer' => $this->trimmedString($row['customer'] ?? null),
            'subject_label' => $this->trimmedString($row['subject_label'] ?? null),
            'source_note' => $this->trimmedString($row['source_note'] ?? null),
        ];
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];
        foreach (['activity_code', 'amount', 'unit', 'period_start', 'period_end', 'data_quality'] as $field) {
            if (($row[$field] ?? null) === null) {
                $issues[] = $this->requiredIssue($field);
            }
        }
        if (($row['activity_code'] ?? null) !== null && ! $row['known_code']) {
            $issues[] = new ValidationIssue(ImportErrorCode::Format, 'activity_code', (string) __('import.error.sustainability.activityCode', ['value' => (string) $row['activity_code']]));
        }
        if (($row['amount'] ?? null) !== null && str_starts_with((string) $row['amount'], '-')) {
            $issues[] = new ValidationIssue(ImportErrorCode::OutOfRange, 'amount', (string) __('import.error.sustainability.negative'));
        }
        if (($row['period_start'] ?? null) !== null && ($row['period_end'] ?? null) !== null && $row['period_end'] < $row['period_start']) {
            $issues[] = new ValidationIssue(ImportErrorCode::OutOfRange, 'period_end', (string) __('import.error.sustainability.period'));
        }
        if (($row['site'] ?? null) !== null && ($row['customer'] ?? null) !== null) {
            $issues[] = new ValidationIssue(ImportErrorCode::Format, 'site', (string) __('import.error.sustainability.subjectBoth'));
        }
        foreach (['unit' => 20, 'subject_label' => 200, 'source_note' => 300] as $field => $max) {
            if (($row[$field] ?? null) !== null && mb_strlen((string) $row[$field]) > $max) {
                $issues[] = $this->tooLongIssue($field, $max);
            }
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            $subject = $this->subject($organization, $row);
            if ($subject instanceof ValidationIssue) {
                return [ImportOutcome::Failed, $subject];
            }

            $exists = SustainabilityActivityRecord::query()
                ->where('organization_id', $organization->id)
                ->where('activity_code', $row['activity_code'])
                ->where('subject_type', $subject?->getMorphClass())
                ->where('subject_id', $subject?->getKey())
                ->whereBetween('period_start', DateRange::days((string) $row['period_start'], (string) $row['period_start']))
                ->whereBetween('period_end', DateRange::days((string) $row['period_end'], (string) $row['period_end']))
                ->exists();
            if ($exists) {
                return [ImportOutcome::Skipped, null];
            }

            SustainabilityActivityRecord::query()->create([
                'organization_id' => $organization->id,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subject !== null ? (string) $subject->getAttribute('name') : $row['subject_label'],
                'activity_code' => $row['activity_code'],
                'amount' => $row['amount'],
                'unit' => $row['unit'],
                'period_start' => $row['period_start'],
                'period_end' => $row['period_end'],
                'data_quality' => $row['data_quality'],
                'source_note' => $row['source_note'],
                'created_by' => auth()->id(),
            ]);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage())];
        }
    }

    /** @param array<string, mixed> $row */
    private function subject(Organization $organization, array $row): Model|ValidationIssue|null {
        if (($row['site'] ?? null) !== null) {
            $site = SustainabilitySite::query()->where('organization_id', $organization->id)
                ->where(fn ($q) => $q->where('code', $row['site'])->orWhere('name', $row['site']))
                ->first();

            return $site ?? new ValidationIssue(ImportErrorCode::FkMissing, 'site', (string) __('import.error.fkMissing.site', ['value' => (string) $row['site']]));
        }
        if (($row['customer'] ?? null) !== null) {
            $customer = Customer::query()->where('organization_id', $organization->id)->where('number', $row['customer'])->first();

            return $customer ?? new ValidationIssue(ImportErrorCode::FkMissing, 'customer', (string) __('import.error.fkMissing.customer', ['number' => (string) $row['customer']]));
        }

        return null;
    }

    /** Code, Beschriftung („Strom (kWh)“) oder Beschriftung ohne Einheit („Strom“). */
    private function activityCode(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        $needle = mb_strtolower($value);
        foreach (SustainabilityActivityRecord::ACTIVITY_CODES as $code) {
            $label = mb_strtolower((string) __('values.' . $code));
            if ($needle === $code || $needle === $label || $needle === trim((string) preg_replace('/\s*\(.*\)$/', '', $label))) {
                return $code;
            }
        }

        return null;
    }

    private function quality(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        $needle = mb_strtolower($value);
        if (in_array($needle, SustainabilityActivityRecord::QUALITIES, true)) {
            return $needle;
        }
        foreach (SustainabilityActivityRecord::QUALITIES as $quality) {
            if ($needle === mb_strtolower((string) __('values.' . $quality))) {
                return $quality;
            }
        }

        return self::QUALITY_ALIASES[$needle] ?? null;
    }
}
