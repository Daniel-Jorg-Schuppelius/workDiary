{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _items.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Timeline-Liste (MVP-010). Erwartet: $items (list<\App\Services\Timeline\TimelineItem>)
--}}
@if (empty($items))
    <x-empty-state compact icon='<span class="material-symbols-outlined">history</span>'
                   :title="__('timeline.empty')"
                   :message="__('timeline.empty_hint')" />
@else
    <ol class="relative space-y-0">
        @foreach ($items as $item)
            <li class="relative flex gap-3 pb-4 last:pb-0" tabindex="0">
                {{-- Icon + vertikale Linie --}}
                <div class="flex flex-col items-center">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-base-300 bg-base-200 text-base-content/70">
                        <x-icon :name="$item->icon" size="1.1rem" />
                    </span>
                    @unless ($loop->last)
                        <span class="w-px flex-1 bg-base-300" aria-hidden="true"></span>
                    @endunless
                </div>
                <div class="min-w-0 flex-1 pt-1">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                        <p class="text-sm font-medium text-base-content">
                            @if ($item->url)
                                <a href="{{ $item->url }}" class="link-hover">{{ $item->title }}</a>
                            @else
                                {{ $item->title }}
                            @endif
                            @if ($item->visibility === \App\Services\Timeline\TimelineItem::VISIBILITY_CUSTOMER)
                                <x-status-badge tone="info" outline class="ml-1 align-middle">{{ __('timeline.visibility.customer') }}</x-status-badge>
                            @endif
                        </p>
                        <span class="text-xs text-base-content/55 whitespace-nowrap">{{ $item->occurredAt?->fdatetime() ?? '—' }}</span>
                    </div>
                    <p class="text-xs text-base-content/60">
                        {{ $item->actor ?? __('timeline.actor_system') }}
                    </p>
                    @if ($item->summary)
                        <p class="mt-0.5 break-words text-sm text-base-content/80">{{ $item->summary }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
