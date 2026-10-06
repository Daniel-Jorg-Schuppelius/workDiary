<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationContractReviewStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Applications;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Stand eines Review-Punkts einer Vertragsverhandlung (Feature 068, MVP-196): offen, gelöst oder als Abweichung angenommen. */
enum ApplicationContractReviewStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Resolved = 'resolved';
    case Accepted = 'accepted';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }
}
