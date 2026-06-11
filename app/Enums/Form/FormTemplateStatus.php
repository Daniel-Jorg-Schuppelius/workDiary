<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormTemplateStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Form;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status einer Formularvorlage (Feature 032). Nur AKTIVE Vorlagen sind
 * ausfüllbar; Entwürfe sind in Arbeit, archivierte fallen aus der
 * Auswahl (bestehende Submissions bleiben über fields_snapshot lesbar).
 */
enum FormTemplateStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string {
        return (string) __('enums.form.template_status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Active => 'success',
            self::Archived => 'ghost',
        };
    }
}
