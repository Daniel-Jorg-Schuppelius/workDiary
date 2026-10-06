<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobApplicationInterviewStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Applications;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Stand eines Bewerbungsgesprächs (Feature 068, MVP-191). */
enum JobApplicationInterviewStatus: string implements HasLabel {
    use HasOptions;

    case Planned = 'planned';
    case Done = 'done';

    /** Geplante Gespräche enden mit der Entscheidung der Akte ({@see \App\Services\Applications\RecruitingService::decide()}). */
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }
}
