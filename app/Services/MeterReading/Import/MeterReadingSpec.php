<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterReadingSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\MeterReading\Import;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\Asset\MeterReading;
use App\Models\Platform\Organization;
use App\Services\Asset\Import\ResolvesAssetCode;
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\AbstractEntitySpec;
use App\Services\Import\Specs\Concerns\ParsesLocalDateTime;
use App\Services\MeterReading\MeterReadingService;
use App\Support\Tz;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Zählerstände per Datei (MVP-962), z. B. Betriebsstunden aus Telematik- oder
 * Messgeräte-Exporten. Gerät über Asset-, Inventar- oder Seriennummer; ein
 * Stand zum selben Zeitpunkt wird übersprungen, ein rückläufiger abgelehnt.
 */
class MeterReadingSpec extends AbstractEntitySpec {
    use ParsesLocalDateTime;
    use ResolvesAssetCode;

    public function __construct(private readonly MeterReadingService $readings) {}

    public function entity(): ImportEntity {
        return ImportEntity::MeterReadings;
    }

    public function columns(): array {
        return ['asset', 'date', 'time', 'value', 'unit', 'is_estimated', 'notes'];
    }

    public function requiredColumns(): array {
        return ['asset', 'date', 'value', 'unit'];
    }

    public function headerAliases(): array {
        return [
            'gerät' => 'asset', 'geraet' => 'asset', 'objekt' => 'asset', 'inventarnummer' => 'asset', 'seriennummer' => 'asset', 'asset_no' => 'asset',
            'datum' => 'date', 'uhrzeit' => 'time', 'zeit' => 'time',
            'stand' => 'value', 'zählerstand' => 'value', 'zaehlerstand' => 'value', 'betriebsstunden' => 'value', 'wert' => 'value',
            'einheit' => 'unit', 'geschätzt' => 'is_estimated', 'geschaetzt' => 'is_estimated', 'notiz' => 'notes', 'bemerkung' => 'notes',
        ];
    }

    public function normalize(array $row): array {
        return [
            'asset' => $this->trimmedString($row['asset'] ?? null),
            'date' => $this->normalizeImportDate($this->trimmedString($row['date'] ?? null)),
            'time' => $this->normalizeImportTime($this->trimmedString($row['time'] ?? null)) ?? '12:00',
            'value' => $this->decimal($this->trimmedString($row['value'] ?? null)),
            'unit' => $this->trimmedString($row['unit'] ?? null),
            'is_estimated' => ($raw = $this->trimmedString($row['is_estimated'] ?? null)) === null ? false : (bool) StringHelper::parseBool($raw),
            'notes' => $this->trimmedString($row['notes'] ?? null),
        ];
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];
        foreach (['asset', 'date', 'value', 'unit'] as $field) {
            if (($row[$field] ?? null) === null) {
                $issues[] = $this->requiredIssue($field);
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
            $readAt = $this->localToUtc((string) $row['date'], (string) $row['time'], Tz::ofOrganization($organization));
            $exists = MeterReading::query()->where('asset_id', $asset->id)->where('read_at', $readAt)->exists();
            if ($exists) {
                return [ImportOutcome::Skipped, null];
            }
            $this->readings->record($asset, null, [
                'read_at' => Carbon::instance($readAt),
                'value' => (string) $row['value'],
                'unit' => (string) $row['unit'],
                'is_estimated' => (bool) $row['is_estimated'],
                'notes' => $row['notes'],
            ]);

            return [ImportOutcome::Created, null];
        } catch (InvalidArgumentException) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::OutOfRange, 'value', (string) __('import.error.meterReading.decreasing'))];
        } catch (Throwable $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage())];
        }
    }
}
