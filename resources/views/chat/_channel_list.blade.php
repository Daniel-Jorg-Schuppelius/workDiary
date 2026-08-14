{{--
  Created on   : Sun Jun 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _channel_list.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kanalliste (Sidebar). Self-contained, auch für Live-Refresh per Endpoint.
     Erwartet: $channels, $activeChannel, $me. --}}
@php
    $listIcon = ['channel' => 'tag', 'group' => 'groups', 'direct' => 'person'];
    $listTitle = function ($c) use ($me) {
        if ($c->type === 'direct') {
            return $c->members->firstWhere('id', '!=', $me?->id)?->name ?? __('Direktnachricht');
        }
        return $c->name;
    };
@endphp
@forelse ($channels as $c)
    @php $unread = $me ? $c->unreadCountFor($me) : 0; $active = $activeChannel && $activeChannel->id === $c->id; @endphp
    <a href="{{ route('chat.show', $c) }}"
       class="flex items-center gap-2 rounded-box px-2 py-1.5 text-sm {{ $active ? 'bg-primary/10 text-primary' : 'hover:bg-base-200' }}">
        <x-icon :name="$listIcon[$c->type] ?? 'tag'" size="1rem" class="opacity-60" />
        <span class="flex-1 truncate {{ $unread ? 'font-semibold' : '' }}">{{ $listTitle($c) }}</span>
        @if ($unread)<span class="badge badge-primary badge-sm tabular-nums">{{ $unread }}</span>@endif
    </a>
@empty
    <p class="px-2 py-4 text-sm text-base-content/50">{{ __('Noch keine Kanäle.') }}</p>
@endforelse
