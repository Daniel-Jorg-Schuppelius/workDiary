<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatReminderWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Chat\WorkQueue;

use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Chat\Reminder;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;

/** Noch nicht ausgelöste Erinnerungen, die der Nutzer an Chat-Nachrichten gesetzt hat. */
final class ChatReminderWorkItemSource implements WorkItemSource {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'chat_reminder';
    }

    public function label(): string {
        return (string) __('work-queue.source.chat_reminder');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Reminder;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled('chat.messages.show');
    }

    public function items(User $viewer, Organization $organization): iterable {
        // Erinnerungen tragen keine Organisation; sie gilt über Kanal und Nachricht.
        $reminders = Reminder::query()
            ->where('user_id', $viewer->id)
            ->whereNull('sent_at')
            ->whereHas('channel', static fn ($q) => $q->where('organization_id', $organization->id))
            ->with('message:id,channel_id,body')
            ->orderBy('remind_at')
            ->get();

        foreach ($reminders as $reminder) {
            if ($reminder->message === null) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $reminder->getMorphClass() . ':' . $reminder->id,
                title: StringHelper::truncate((string) $reminder->message->body, 80, '…') ?: (string) __('work-queue.item.chat_reminder'),
                url: route('chat.messages.show', $reminder->message),
                dueAt: CarbonImmutable::instance($reminder->remind_at),
                personal: true,
            );
        }
    }
}
