<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VatIdField.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\Fields;

use App\Services\Fields\Contracts\FieldExtension;
use App\Services\Fields\FieldDefinition;
use Closure;
use CommonToolkit\Helper\Data\VatNumberHelper;

/**
 * Fachtyp „USt-IdNr.“ (Feature 200, MVP-1675): Textfeld, das Aufbau und —
 * wo das Toolkit sie kennt — die Prüfziffer prüft
 * ({@see VatNumberHelper::validateVatId()} streng). Keine qualifizierte
 * Bestätigung beim BZSt. Gespeichert wird die normalisierte Schreibweise.
 */
final class VatIdField implements FieldExtension {
    public const string KEY = 'vat_id';

    public function key(): string {
        return self::KEY;
    }

    public function rules(FieldDefinition $field): array {
        return ['string', 'max:20', static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! VatNumberHelper::validateVatId($value, true)) {
                $fail((string) __('lead_intake.vat_id.invalid'));
            }
        }];
    }

    public function normalize(FieldDefinition $field, mixed $raw): mixed {
        return is_string($raw) && trim($raw) !== '' ? VatNumberHelper::normalize($raw) : null;
    }

    public function display(FieldDefinition $field, mixed $value): string {
        return is_string($value) && $value !== '' ? $value : '—';
    }

    public function inputView(): ?string {
        return null;
    }
}
