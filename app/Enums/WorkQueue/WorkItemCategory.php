<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkItemCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\WorkQueue;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art eines Vorgangs im Arbeitsvorrat — Filter der Liste. */
enum WorkItemCategory: string implements HasLabel {
    use HasOptions;

    case Approval = 'approval';
    case Inbox = 'inbox';
    case Task = 'task';
    case Reminder = 'reminder';

    public function label(): string {
        return (string) __('enums.work_queue.category.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::Approval => 'rule',
            self::Inbox => 'inbox',
            self::Task => 'task_alt',
            self::Reminder => 'notifications_active',
        };
    }
}
