<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Enums\Project\ProjectStatus;
use App\Models\{Customer, Organization, Project};
use App\Services\Import\{ImportOutcome, ValidationIssue};
use Throwable;

/**
 * CSV-Spezifikation für den Projekt-Import (MVP-049).
 *
 * Idempotenz-Schlüssel: (organization_id, number). Die Kunden-Zuordnung
 * erfolgt per `customer_number` (Kundennummer) — fehlt der Kunde, wird
 * die Zeile mit {@see ImportErrorCode::FkMissing} markiert.
 */
class ProjectSpec extends AbstractEntitySpec {
    public function entity(): ImportEntity {
        return ImportEntity::Projects;
    }

    public function columns(): array {
        return [
            'name',
            'number',
            'customer_number',
            'description',
            'color',
            'status',
            'starts_on',
            'ends_on',
            'hourly_rate',
            'internal_rate',
            'budget',
            'time_budget',
            'billable',
        ];
    }

    public function requiredColumns(): array {
        return ['name', 'customer_number'];
    }

    public function headerAliases(): array {
        return [
            'projekt' => 'name',
            'nummer' => 'number',
            'projektnummer' => 'number',
            'kundennummer' => 'customer_number',
            'beschreibung' => 'description',
            'farbe' => 'color',
            'status' => 'status',
            'beginn' => 'starts_on',
            'startdatum' => 'starts_on',
            'ende' => 'ends_on',
            'enddatum' => 'ends_on',
            'stundensatz' => 'hourly_rate',
            'budget' => 'budget',
            'zeitbudget' => 'time_budget',
            'abrechenbar' => 'billable',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'billable' => $raw === null || $raw === '' ? null : $this->boolish($raw),
                'hourly_rate', 'internal_rate', 'budget' => $this->decimal($this->trimmedString($raw)),
                'time_budget' => ($v = $this->trimmedString($raw)) !== null && ctype_digit($v) ? (int) $v : null,
                'starts_on', 'ends_on' => $this->parseDate($this->trimmedString($raw)),
                'status' => $this->normStatus($this->trimmedString($raw)),
                default => $this->trimmedString($raw),
            };
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];

        if (($row['name'] ?? null) === null) {
            $issues[] = $this->requiredIssue('name');
        }
        if (($row['customer_number'] ?? null) === null) {
            $issues[] = $this->requiredIssue('customer_number');
        } else {
            $exists = Customer::query()
                ->where('organization_id', $organization->id)
                ->where('number', $row['customer_number'])
                ->exists();
            if (! $exists) {
                $issues[] = new ValidationIssue(
                    ImportErrorCode::FkMissing,
                    'customer_number',
                    (string) __('import.error.fkMissing.customer', ['number' => $row['customer_number']]),
                );
            }
        }

        if (! empty($row['status']) && ! ($row['status'] instanceof ProjectStatus)) {
            $issues[] = $this->formatIssue('status', (string) __('import.error.format.enum'));
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            $customer = Customer::query()
                ->where('organization_id', $organization->id)
                ->where('number', $row['customer_number'])
                ->first();
            if ($customer === null) {
                return [
                    ImportOutcome::Failed,
                    new ValidationIssue(
                        ImportErrorCode::FkMissing,
                        'customer_number',
                        (string) __('import.error.fkMissing.customer', ['number' => $row['customer_number']]),
                    ),
                ];
            }

            $payload = array_filter($row, static fn($v): bool => $v !== null);
            unset($payload['customer_number']);
            $payload['organization_id'] = $organization->id;
            $payload['customer_id'] = $customer->id;
            $payload['status'] ??= ProjectStatus::Active;

            $existing = null;
            $number = $payload['number'] ?? null;
            if ($number !== null && $number !== '') {
                $existing = Project::query()
                    ->where('organization_id', $organization->id)
                    ->where('number', $number)
                    ->first();
            }

            if ($existing !== null) {
                $existing->fill($payload)->save();

                return [ImportOutcome::Updated, null];
            }

            Project::create($payload);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [
                ImportOutcome::Failed,
                new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage()),
            ];
        }
    }

    private function parseDate(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y'] as $fmt) {
            $d = \DateTimeImmutable::createFromFormat('!' . $fmt, $value);
            if ($d !== false) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    private function normStatus(?string $value): ?ProjectStatus {
        if ($value === null) {
            return null;
        }

        return ProjectStatus::tryFrom(mb_strtolower($value));
    }
}
