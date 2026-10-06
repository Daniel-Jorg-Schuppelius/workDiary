<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationRequirementStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Applications;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Stand einer Unterlagenanforderung der Ausschreibungsakte (Feature 068, MVP-185). */
enum ApplicationRequirementStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';
    case NotApplicable = 'not_applicable';

    /**
     * Stände, mit denen eine Pflichtunterlage die Abgabe nicht mehr aufhält.
     *
     * @return list<self>
     */
    public static function settled(): array {
        return [self::Done, self::NotApplicable];
    }

    public function label(): string {
        return (string) __('values.' . $this->value);
    }
}
