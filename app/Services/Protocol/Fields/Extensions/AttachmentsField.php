<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentsField.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Protocol\Fields\Extensions;

use App\Services\Fields\Contracts\FieldExtension;
use App\Services\Fields\FieldDefinition;

/**
 * Pflichtfoto/-dokument eines Protokolls: `value_json.attachment_ids`
 * (Liste), Mindest-/Höchstzahl aus `min_count`/`max_count` (Definition
 * min/max). Phasen-Mindestmengen prüft der Protokollvalidator weiterhin
 * gegen die Foto-Tabelle.
 */
class AttachmentsField implements FieldExtension {
    public const KEY = 'protocol.attachments';

    public function key(): string {
        return self::KEY;
    }

    public function rules(FieldDefinition $field): array {
        return ['array', function (string $attribute, mixed $value, \Closure $fail) use ($field): void {
            if (! is_array($value) || $value === []) {
                $fail((string) __('protocol.validation.attachments.required'));

                return;
            }
            if ($field->min !== null && count($value) < (int) $field->min) {
                $fail((string) __('protocol.validation.attachments.min', ['min' => (int) $field->min]));
            }
            if ($field->max !== null && count($value) > (int) $field->max) {
                $fail((string) __('protocol.validation.attachments.max', ['max' => (int) $field->max]));
            }
        }];
    }

    public function normalize(FieldDefinition $field, mixed $raw): mixed {
        return is_array($raw) ? array_values(array_map('intval', array_filter($raw, 'is_numeric'))) : null;
    }

    public function display(FieldDefinition $field, mixed $value): string {
        $count = is_array($value) ? count($value) : 0;

        return $count === 0 ? '—' : (string) trans_choice('fields.value.attachments', $count, ['count' => $count]);
    }

    public function inputView(): ?string {
        return null;
    }
}
