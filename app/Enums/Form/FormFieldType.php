<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormFieldType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Form;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Feldtypen des Formularsystems (Feature 032, MVP). Foto/Datei/Unterschrift
 * sind bewusst Folgeausbau (siehe Feature-Doku Out-of-Scope).
 */
enum FormFieldType: string implements HasLabel {
    use HasOptions;

    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';

    public function label(): string {
        return (string) __('enums.form.field_type.' . $this->value);
    }

    /** Braucht dieser Typ eine Optionsliste? */
    public function needsOptions(): bool {
        return $this === self::Select;
    }

    /** Erlaubt dieser Typ eine Einheit (z. B. kWh, °C)? */
    public function supportsUnit(): bool {
        return $this === self::Number;
    }
}
