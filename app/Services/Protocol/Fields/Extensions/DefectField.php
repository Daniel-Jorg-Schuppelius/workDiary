<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DefectField.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Protocol\Fields\Extensions;

use App\Services\Fields\Contracts\FieldExtension;
use App\Services\Fields\FieldDefinition;
use Illuminate\Support\Str;

/**
 * Mangel-Punkt eines Protokolls (MVP-021 §3.12): Schweregrad + Beschreibung,
 * optional Kategorie und verknüpfter Open-Issue. Wert ist das `value_json`
 * des Punkts (hash-kanonisch, unverändert).
 */
class DefectField implements FieldExtension {
    public const KEY = 'protocol.defect';

    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public function key(): string {
        return self::KEY;
    }

    public function rules(FieldDefinition $field): array {
        return ['array', function (string $attribute, mixed $value, \Closure $fail): void {
            $value = is_array($value) ? $value : [];
            if (! in_array($value['severity'] ?? null, self::SEVERITIES, true)) {
                $fail((string) __('protocol.validation.defect.severity'));
            }
            if (trim((string) ($value['description'] ?? '')) === '') {
                $fail((string) __('protocol.validation.defect.description'));
            }
        }];
    }

    public function normalize(FieldDefinition $field, mixed $raw): mixed {
        return is_array($raw) ? $raw : null;
    }

    public function display(FieldDefinition $field, mixed $value): string {
        if (! is_array($value) || trim((string) ($value['description'] ?? '')) === '') {
            return '—';
        }
        $severity = is_scalar($value['severity'] ?? null) ? (string) $value['severity'] : '';

        return trim(($severity !== '' ? Str::ucfirst($severity) . ': ' : '') . Str::limit((string) $value['description'], 160));
    }

    public function inputView(): ?string {
        return 'protocols.fields.defect';
    }
}
