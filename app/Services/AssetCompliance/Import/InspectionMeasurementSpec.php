<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectionMeasurementSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetCompliance\Import;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\AssetCompliance\{AssetInspectionEvent, AssetMeasurementValue};
use App\Models\Platform\Organization;
use App\Services\Asset\Import\ResolvesAssetCode;
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\AbstractEntitySpec;
use App\Services\Import\Specs\Concerns\ParsesLocalDateTime;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Prüfmesswerte per Datei (MVP-974), z. B. aus dem Export eines
 * Gerätetesters. Die Zeile nennt Gerät und Prüfdatum (optional das
 * Prüfprofil); der Messwert hängt sich an diese Prüfung. Prüfungen bleiben
 * unverändert — Messwerte werden nur ergänzt, gleiche (Bezeichnung und
 * Messzeit) übersprungen.
 */
class InspectionMeasurementSpec extends AbstractEntitySpec {
    use ParsesLocalDateTime;
    use ResolvesAssetCode;

    public function entity(): ImportEntity {
        return ImportEntity::InspectionMeasurements;
    }

    public function columns(): array {
        return ['asset', 'date', 'profile', 'label', 'value', 'unit', 'time'];
    }

    public function requiredColumns(): array {
        return ['asset', 'date', 'label', 'value'];
    }

    public function headerAliases(): array {
        return [
            'gerät' => 'asset', 'geraet' => 'asset', 'prüfmittel' => 'asset', 'pruefmittel' => 'asset', 'inventarnummer' => 'asset', 'seriennummer' => 'asset',
            'prüfdatum' => 'date', 'pruefdatum' => 'date', 'datum' => 'date', 'prüfprofil' => 'profile', 'pruefprofil' => 'profile', 'profil' => 'profile',
            'messgröße' => 'label', 'messgroesse' => 'label', 'messung' => 'label', 'bezeichnung' => 'label', 'messwert' => 'value', 'wert' => 'value',
            'einheit' => 'unit', 'uhrzeit' => 'time', 'messzeit' => 'time', 'zeit' => 'time',
        ];
    }

    public function normalize(array $row): array {
        return [
            'asset' => $this->trimmedString($row['asset'] ?? null),
            'date' => $this->normalizeImportDate($this->trimmedString($row['date'] ?? null)),
            'profile' => $this->trimmedString($row['profile'] ?? null),
            'label' => $this->trimmedString($row['label'] ?? null),
            'value' => $this->decimal($this->trimmedString($row['value'] ?? null)),
            'unit' => $this->trimmedString($row['unit'] ?? null),
            'time' => $this->normalizeImportTime($this->trimmedString($row['time'] ?? null)),
        ];
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];
        foreach (['asset', 'date', 'label', 'value'] as $field) {
            if (($row[$field] ?? null) === null) {
                $issues[] = $this->requiredIssue($field);
            }
        }
        foreach (['label' => 255, 'unit' => 30] as $field => $max) {
            if (($row[$field] ?? null) !== null && mb_strlen((string) $row[$field]) > $max) {
                $issues[] = $this->tooLongIssue($field, $max);
            }
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            $asset = $this->assetByCode($organization, (string) $row['asset']);
            if ($asset === null) {
                return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::FkMissing, 'asset', (string) __('import.error.fkMissing.asset', ['number' => (string) $row['asset']]))];
            }

            $tz = Tz::ofOrganization($organization);
            $dayStart = CarbonImmutable::parse((string) $row['date'] . ' 00:00:00', $tz)->utc();
            $events = AssetInspectionEvent::query()
                ->where('asset_id', $asset->id)
                ->where('performed_at', '>=', $dayStart)
                ->where('performed_at', '<', $dayStart->addDay())
                // Korrigierte Prüfungen zählen nicht; die Korrektur ist die gültige Fassung.
                ->whereNotIn('id', AssetInspectionEvent::query()->whereNotNull('supersedes_id')->select('supersedes_id'))
                ->when($row['profile'] !== null, fn ($q) => $q->whereHas('assignment.profile', fn ($p) => $p->where(fn ($w) => $w->where('code', $row['profile'])->orWhere('name', $row['profile']))))
                ->limit(2)
                ->get();
            if ($events->isEmpty()) {
                return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::FkMissing, 'date', (string) __('import.error.fkMissing.inspection', ['date' => (string) $row['date'], 'asset' => (string) $row['asset']]))];
            }
            if ($events->count() > 1) {
                return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Format, 'profile', (string) __('import.error.measurement.ambiguous', ['date' => (string) $row['date']]))];
            }

            /** @var AssetInspectionEvent $event */
            $event = $events->first();
            $measuredAt = $row['time'] !== null ? $this->localToUtc((string) $row['date'], (string) $row['time'], $tz) : $event->performed_at->toImmutable();
            $exists = AssetMeasurementValue::query()
                ->where('asset_inspection_event_id', $event->id)
                ->where('label', $row['label'])
                ->where('measured_at', $measuredAt)
                ->exists();
            if ($exists) {
                return [ImportOutcome::Skipped, null];
            }

            $event->measurements()->create([
                'organization_id' => $event->organization_id,
                'label' => (string) $row['label'],
                'value' => (string) $row['value'],
                'unit' => $row['unit'],
                'measured_at' => $measuredAt,
            ]);
            $asset->audit('assetCompliance.measurementImported', ['event_id' => $event->id, 'label' => $row['label'], 'value' => $row['value'], 'unit' => $row['unit']]);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage())];
        }
    }
}
