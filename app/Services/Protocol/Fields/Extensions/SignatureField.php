<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SignatureField.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Protocol\Fields\Extensions;

use App\Services\Fields\Contracts\FieldExtension;
use App\Services\Fields\FieldDefinition;

/** Unterschrift eines Protokollpunkts: Verweis `value_json.signature_id` auf die Signaturzeile. */
class SignatureField implements FieldExtension {
    public const KEY = 'protocol.signature';

    public function key(): string {
        return self::KEY;
    }

    public function rules(FieldDefinition $field): array {
        return [function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_int($value)) {
                $fail((string) __('protocol.validation.signature.missing'));
            }
        }];
    }

    public function normalize(FieldDefinition $field, mixed $raw): mixed {
        return is_int($raw) ? $raw : (is_numeric($raw) ? (int) $raw : null);
    }

    public function display(FieldDefinition $field, mixed $value): string {
        return $value ? (string) __('fields.value.signed') : '—';
    }

    public function inputView(): ?string {
        return null;
    }
}
