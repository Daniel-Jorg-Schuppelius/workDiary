<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetPositionSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset\Import;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Events\Asset\AssetPositionRecorded;
use App\Models\Asset\AssetPosition;
use App\Models\Platform\Organization;
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\AbstractEntitySpec;
use App\Services\Import\Specs\Concerns\ParsesLocalDateTime;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Geo\GeoHelper;
use Throwable;

/**
 * Gerätepositionen aus Telematik-Exporten (MVP-975). Zeitpunkt als Datum +
 * Uhrzeit (Ortszeit der Organisation) oder als Zeitstempel mit Zone
 * (`2026-09-01T07:00:00Z`). Die jüngste Position wird am Gerät fortgeschrieben;
 * dieselbe Position zum selben Zeitpunkt gilt als Dublette.
 */
class AssetPositionSpec extends AbstractEntitySpec {
    use ParsesLocalDateTime;
    use ResolvesAssetCode;

    public function entity(): ImportEntity {
        return ImportEntity::AssetPositions;
    }

    public function columns(): array {
        return ['asset', 'date', 'time', 'timestamp', 'lat', 'lng'];
    }

    public function requiredColumns(): array {
        return ['asset', 'lat', 'lng'];
    }

    public function headerAliases(): array {
        return [
            'gerät' => 'asset', 'geraet' => 'asset', 'objekt' => 'asset', 'inventarnummer' => 'asset', 'seriennummer' => 'asset', 'device' => 'asset',
            'datum' => 'date', 'uhrzeit' => 'time', 'zeit' => 'time', 'zeitstempel' => 'timestamp', 'zeitpunkt' => 'timestamp',
            'breite' => 'lat', 'breitengrad' => 'lat', 'latitude' => 'lat', 'länge' => 'lng', 'laenge' => 'lng', 'längengrad' => 'lng', 'laengengrad' => 'lng', 'longitude' => 'lng', 'lon' => 'lng',
        ];
    }

    public function normalize(array $row): array {
        $date = $this->normalizeImportDate($this->trimmedString($row['date'] ?? null));
        $time = $this->normalizeImportTime($this->trimmedString($row['time'] ?? null));
        $absolute = null;
        $stamp = $this->trimmedString($row['timestamp'] ?? null);
        if ($stamp !== null) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/', $stamp) === 1) {
                try {
                    $absolute = CarbonImmutable::parse($stamp)->utc()->format('Y-m-d H:i:s');
                } catch (Throwable) {
                    $absolute = null;
                }
            } elseif (preg_match('/^(\S+)[ T](\d{1,2}:\d{2}(?::\d{2})?)$/', $stamp, $parts) === 1) {
                $date ??= $this->normalizeImportDate($parts[1]);
                $time ??= $this->normalizeImportTime($parts[2]);
            }
        }

        return [
            'asset' => $this->trimmedString($row['asset'] ?? null),
            'date' => $date,
            'time' => $time,
            'absolute' => $absolute,
            'lat' => $this->decimal($this->trimmedString($row['lat'] ?? null)),
            'lng' => $this->decimal($this->trimmedString($row['lng'] ?? null)),
        ];
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];
        foreach (['asset', 'lat', 'lng'] as $field) {
            if (($row[$field] ?? null) === null) {
                $issues[] = $this->requiredIssue($field);
            }
        }
        if (($row['absolute'] ?? null) === null && ($row['date'] ?? null) === null) {
            $issues[] = $this->requiredIssue('date');
        }
        if (($row['lat'] ?? null) !== null && ($row['lng'] ?? null) !== null && ! GeoHelper::isValidCoordinate((float) $row['lat'], (float) $row['lng'])) {
            $issues[] = new ValidationIssue(ImportErrorCode::OutOfRange, 'lat', (string) __('import.error.position.coordinates'));
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            $asset = $this->assetByCode($organization, (string) $row['asset']);
            if ($asset === null) {
                return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::FkMissing, 'asset', (string) __('import.error.fkMissing.asset', ['number' => (string) $row['asset']]))];
            }
            $recordedAt = $row['absolute'] !== null
                ? CarbonImmutable::parse((string) $row['absolute'], 'UTC')
                : $this->localToUtc((string) $row['date'], (string) ($row['time'] ?? '12:00'), Tz::ofOrganization($organization));
            if (AssetPosition::query()->where('asset_id', $asset->id)->where('recorded_at', $recordedAt)->exists()) {
                return [ImportOutcome::Skipped, null];
            }

            $latest = AssetPosition::query()->where('asset_id', $asset->id)->max('recorded_at');
            $position = AssetPosition::query()->create([
                'organization_id' => $asset->organization_id,
                'asset_id' => $asset->id,
                'recorded_at' => $recordedAt,
                'lat' => (string) $row['lat'],
                'lng' => (string) $row['lng'],
                'source' => 'import',
                'created_by' => auth()->id(),
            ]);
            if ($latest === null || $recordedAt->greaterThanOrEqualTo(CarbonImmutable::parse((string) $latest, 'UTC'))) {
                $asset->forceFill(['location_lat' => (string) $row['lat'], 'location_lng' => (string) $row['lng']])->save();
            }
            AssetPositionRecorded::dispatch($position);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage())];
        }
    }
}
