<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatUnreadWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\Chat\Channel;
use App\Models\User;
use Illuminate\Contracts\View\View;

/** Ungelesene Chat-Nachrichten, nach Kanal aufgeschlüsselt. */
class ChatUnreadWidget extends Widget {
    public function key(): string {
        return 'chat-unread';
    }

    public function label(): string {
        return (string) __('Ungelesene Chats');
    }

    public function icon(): string {
        return 'forum';
    }

    public function defaultOrder(): int {
        return 105;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Activity;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.chat_unread.description');
    }

    public function requiredModule(): ?string {
        return 'module.chat';
    }

    public function render(User $user): View|string {
        $channels = Channel::query()
            ->whereHas('members', fn ($q) => $q->whereKey($user->getKey()))
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (Channel $channel): array => [
                'channel' => $channel,
                'unread' => $channel->unreadCountFor($user),
            ])
            ->filter(fn (array $row): bool => $row['unread'] > 0)
            ->values();

        return view('dashboard.widgets.chat-unread', [
            'rows' => $channels,
            'total' => Channel::unreadTotalFor($user),
        ]);
    }
}
