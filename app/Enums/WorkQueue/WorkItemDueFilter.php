<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkItemDueFilter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\WorkQueue;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use Carbon\CarbonImmutable;

/** Filter „Fälligkeit“ des Arbeitsvorrats (MVP-1617); „diese Woche“ reicht von heute bis Sonntag. */
enum WorkItemDueFilter: string implements HasLabel {
    use HasOptions;

    case Overdue = 'overdue';
    case Today = 'today';
    case Week = 'week';
    case None = 'none';

    public function matches(?CarbonImmutable $dueAt, CarbonImmutable $now): bool {
        if ($dueAt === null) {
            return $this === self::None;
        }

        return match ($this) {
            self::Overdue => $dueAt->lt($now->startOfDay()),
            self::Today => $dueAt->isSameDay($now),
            self::Week => $dueAt->gte($now->startOfDay()) && $dueAt->lte($now->endOfWeek(CarbonImmutable::SUNDAY)),
            self::None => false,
        };
    }

    public function label(): string {
        return (string) __('enums.work_queue.due.' . $this->value);
    }
}
