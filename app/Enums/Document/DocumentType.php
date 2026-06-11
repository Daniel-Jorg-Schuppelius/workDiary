<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Document;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum DocumentType: string implements HasLabel {
    use HasOptions;

    case Contract = 'contract';
    case TestReport = 'testReport';
    case Certificate = 'certificate';
    case Manual = 'manual';
    case Datasheet = 'datasheet';
    case ManufacturerDoc = 'manufacturerDoc';
    case Permit = 'permit';
    case Insurance = 'insurance';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.document.type.' . $this->value);
    }

    /** Material-Symbols-Icon für Listen/Panels. */
    public function icon(): string {
        return match ($this) {
            self::Contract => 'history_edu',
            self::TestReport => 'fact_check',
            self::Certificate => 'workspace_premium',
            self::Manual => 'menu_book',
            self::Datasheet => 'description',
            self::ManufacturerDoc => 'precision_manufacturing',
            self::Permit => 'approval',
            self::Insurance => 'shield',
            self::Other => 'draft',
        };
    }
}
