<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DpiaStepStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines Schritts im geführten DSFA-Workflow (Nachtrag 043a). */
enum DpiaStepStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Pending = 'pending';
    case Done = 'done';

    public function label(): string {
        return match ($this) {
            self::Pending => __('enums.privacy.dpia_step_status.pending'),
            self::Done => __('enums.privacy.dpia_step_status.done'),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Pending => [self::Done],
            self::Done => [],
        };
    }
}
