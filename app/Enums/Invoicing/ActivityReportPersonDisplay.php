<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityReportPersonDisplay.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use CommonToolkit\Helper\Data\StringHelper;

/** Wie Mitarbeitende im Leistungsnachweis erscheinen (Feature 196, D21). */
enum ActivityReportPersonDisplay: string implements HasLabel {
    use HasOptions;

    case FullName = 'full_name';
    case Initials = 'initials';
    case None = 'none';

    public function label(): string {
        return match ($this) {
            self::FullName => (string) __('enums.invoicing.activity_report_person_display.full_name'),
            self::Initials => (string) __('enums.invoicing.activity_report_person_display.initials'),
            self::None => (string) __('enums.invoicing.activity_report_person_display.none'),
        };
    }

    public function format(?string $name): ?string {
        $name = trim((string) $name);
        if ($name === '' || $this === self::None) {
            return null;
        }

        return $this === self::FullName ? $name : StringHelper::printableInitials($name);
    }
}
