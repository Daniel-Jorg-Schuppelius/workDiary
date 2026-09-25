<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeasurementSeriesField.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Protocol\Fields\Extensions;

use App\Services\Fields\Contracts\FieldExtension;
use App\Services\Fields\FieldDefinition;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Messreihe mit Zeitstempeln (`value_json.samples = [{value, at}, …]`).
 */
class MeasurementSeriesField implements FieldExtension {
    public const KEY = 'protocol.measurement';

    public function key(): string {
        return self::KEY;
    }

    public function rules(FieldDefinition $field): array {
        return ['array', function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_array($value) || $value === []) {
                $fail((string) __('protocol.validation.measurement.empty'));

                return;
            }
            foreach ($value as $sample) {
                if (! is_array($sample) || ! array_key_exists('value', $sample) || ! array_key_exists('at', $sample)) {
                    $fail((string) __('protocol.validation.measurement.invalidSample'));

                    return;
                }
            }
        }];
    }

    public function normalize(FieldDefinition $field, mixed $raw): mixed {
        return is_array($raw) ? array_values($raw) : null;
    }

    public function display(FieldDefinition $field, mixed $value): string {
        if (! is_array($value) || $value === []) {
            return '—';
        }
        $parts = [];
        foreach ($value as $sample) {
            if (is_array($sample) && is_numeric($sample['value'] ?? null)) {
                $parts[] = NumberHelper::toGermanFormat((float) $sample['value'], 2, trimTrailingZeros: true);
            }
        }
        $unit = $field->unit !== null ? ' ' . $field->unit : '';

        return count($parts) . ' × ' . ($parts === [] ? '—' : implode(', ', array_slice($parts, 0, 8)) . (count($parts) > 8 ? ', …' : '') . $unit);
    }

    public function inputView(): ?string {
        return 'protocols.fields.measurement';
    }
}
