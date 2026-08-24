<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterReadingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\MeterReading;

use App\Models\{Asset, MeterReading, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MeterReadingService {
    use \App\Services\Concerns\ParsesMixedDate;

    /** @param array<string, mixed> $payload */
    public function record(Asset $asset, User $actor, array $payload): MeterReading {
        $readAt = $this->parseDate($payload['read_at'] ?? null) ?? Carbon::now();
        $value = $this->parseDecimal($payload['value'] ?? null, 'value');
        $unit = trim((string) ($payload['unit'] ?? ''));
        if ($unit === '') {
            throw new InvalidArgumentException('unit is required');
        }

        $previous = MeterReading::query()
            ->where('asset_id', $asset->id)
            ->where('read_at', '<', $readAt)
            ->orderByDesc('read_at')
            ->first();

        $previousValue = null;
        $consumption = null;
        if ($previous !== null) {
            $previousValue = (float) $previous->value;
            if ($value + 1e-9 < $previousValue) {
                throw new InvalidArgumentException('value must be greater than or equal to previous reading');
            }
            $consumption = $value - $previousValue;
        }

        $reading = new MeterReading([
            'organization_id' => $asset->organization_id,
            'asset_id' => $asset->id,
            'read_at' => $readAt,
            'value' => number_format($value, 4, '.', ''),
            'unit' => $unit,
            'previous_value' => $previousValue !== null ? number_format($previousValue, 4, '.', '') : null,
            'consumption' => $consumption !== null ? number_format($consumption, 4, '.', '') : null,
            'read_by_user_id' => $actor->id,
            'photo_path' => $payload['photo_path'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'is_estimated' => (bool) ($payload['is_estimated'] ?? false),
        ]);

        DB::transaction(function () use ($reading): void {
            $reading->save();
        });

        $reading->audit('meter_reading.recorded', [
            'asset_id' => $reading->asset_id,
            'value' => $reading->value,
            'unit' => $reading->unit,
            'consumption' => $reading->consumption,
        ]);

        return $reading->refresh();
    }

    public function latestForAsset(Asset $asset): ?MeterReading {
        return MeterReading::query()
            ->where('asset_id', $asset->id)
            ->orderByDesc('read_at')
            ->first();
    }

    private function parseDecimal(mixed $value, string $field): float {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException($field . ' is required');
        }
        if (! is_numeric($value)) {
            throw new InvalidArgumentException($field . ' must be numeric');
        }

        return (float) $value;
    }
}
