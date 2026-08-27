{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : chat-unread.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Ungelesene Chats" — Daten: ChatUnreadWidget.
--}}
<x-card :title="__('Ungelesene Chats')" icon="forum" :count="$total">
    <x-slot:actions>
        <x-button href="{{ route('chat.index') }}" tone="ghost" size="xs">{{ __('Zum Chat →') }}</x-button>
    </x-slot:actions>

    @if ($rows->isEmpty())
        <x-empty-state compact icon="mark_chat_read"
                       :title="__('Alles gelesen')" :message="__('Keine ungelesenen Nachrichten.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($rows as $row)
                <li class="flex items-center justify-between gap-3 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <a href="{{ route('chat.index', $row['channel']) }}" class="link min-w-0 truncate">
                        {{ $row['channel']->name ?? __('Direktnachricht') }}
                    </a>
                    <span class="badge badge-primary badge-sm tabular-nums">{{ $row['unread'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
