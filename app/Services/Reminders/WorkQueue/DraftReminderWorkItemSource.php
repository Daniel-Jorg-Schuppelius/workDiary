<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DraftReminderWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reminders\WorkQueue;

use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Services\Reminders\ReminderService;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;

/**
 * Liegengebliebene eigene Entwürfe aus den Kopfzeilen-Erinnerungen. Deren
 * Freigabe-Erinnerungen stehen schon als Spesen- und Urlaubsfreigaben im Vorrat.
 */
final class DraftReminderWorkItemSource implements WorkItemSource {
    private const KEYS = ['expense_drafts', 'per_diem_drafts'];

    public function __construct(private readonly ReminderService $reminders) {}

    public function key(): string {
        return 'draft_reminder';
    }

    public function label(): string {
        return (string) __('work-queue.source.draft_reminder');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Reminder;
    }

    public function available(User $viewer): bool {
        return true;
    }

    public function items(User $viewer, Organization $organization): iterable {
        foreach ($this->reminders->for($viewer) as $reminder) {
            if (! in_array($reminder->key, self::KEYS, true)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: 'reminder:' . $reminder->key,
                title: $reminder->title,
                url: $reminder->url,
                detail: $reminder->description,
                personal: true,
                count: $reminder->count,
            );
        }
    }
}
