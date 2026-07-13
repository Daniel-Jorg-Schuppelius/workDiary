{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _timeline.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Ticket-Timeline (Feature 065, MVP-152): Konversation + Status-Audits +
  SLA-Ereignisse + Anhänge gemischt; Typ-Filter-Chips als GET-Parameter.
  Erwartet: $ticket, $timelineItems (list<TimelineItem>), $timelineHasMore (bool),
            $timelineType (string, ''=alle), $timelineLimit (int)
--}}
@php
    $timelineChipLabels = [
        'message' => __('Nachrichten'),
        'status' => __('Status'),
        'sla' => __('SLA'),
        'attachment' => __('Anhänge'),
    ];
@endphp

<div id="timeline">
    {{-- Filter-Chips nach Ereignistyp (serverseitig per Query-Param) --}}
    <nav class="mb-4 flex flex-wrap gap-1.5" aria-label="{{ __('timeline.filter.label') }}">
        <a href="{{ route('service-tickets.show', $ticket) }}#timeline"
           @class(['badge badge-sm', 'badge-primary' => $timelineType === '', 'badge-ghost' => $timelineType !== ''])>
            {{ __('timeline.filter.all') }}
        </a>
        @foreach (\App\Services\Timeline\ServiceTicketTimelineService::TYPES as $typeKey)
            <a href="{{ route('service-tickets.show', [$ticket, 'timeline_type' => $typeKey]) }}#timeline"
               @class(['badge badge-sm', 'badge-primary' => $timelineType === $typeKey, 'badge-ghost' => $timelineType !== $typeKey])>
                {{ $timelineChipLabels[$typeKey] ?? $typeKey }}
            </a>
        @endforeach
    </nav>

    @if (empty($timelineItems))
        <x-empty-state compact icon='<span class="material-symbols-outlined">history</span>'
                       :title="__('timeline.empty')"
                       :message="__('Sobald am Ticket gearbeitet wird, erscheinen die Ereignisse hier.')" />
    @else
        <ol class="relative space-y-0">
            @foreach ($timelineItems as $item)
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
                                @if ($item->type === 'message')
                                    {{-- kind-Badge: interne Notizen deutlich abgesetzt --}}
                                    <x-status-badge :tone="$item->visibility === \App\Services\Timeline\TimelineItem::VISIBILITY_INTERNAL ? 'warning' : 'info'"
                                                    size="xs" class="align-middle">{{ $item->title }}</x-status-badge>
                                @elseif ($item->url)
                                    <a href="{{ $item->url }}" class="link-hover">{{ $item->title }}</a>
                                @else
                                    {{ $item->title }}
                                @endif
                                @if ($item->type !== 'message' && $item->visibility === \App\Services\Timeline\TimelineItem::VISIBILITY_CUSTOMER)
                                    <x-status-badge tone="info" outline class="ml-1 align-middle">{{ __('timeline.visibility.customer') }}</x-status-badge>
                                @endif
                            </p>
                            <span class="text-xs text-base-content/55 whitespace-nowrap">{{ $item->occurredAt?->fdatetime() ?? '—' }}</span>
                        </div>
                        <p class="text-xs text-base-content/60">
                            {{ $item->actor ?? __('timeline.actor_system') }}
                        </p>
                        @if ($item->summary)
                            @if ($item->type === 'attachment' && $item->url)
                                <p class="mt-0.5 text-sm">
                                    <a href="{{ $item->url }}" class="link link-hover inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">download</span>
                                        <span class="wrap-break-word">{{ $item->summary }}</span>
                                    </a>
                                </p>
                            @else
                                <p class="mt-0.5 whitespace-pre-line wrap-break-word text-sm text-base-content/80">{{ $item->summary }}</p>
                            @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($timelineHasMore)
        <div class="mt-4 text-center">
            <x-icon-btn icon="expand_more" tone="outline" size="sm" show-label
                        :href="route('service-tickets.show', array_filter(['ticket' => $ticket, 'timeline_type' => $timelineType ?: null, 'timeline_limit' => $timelineLimit + 50])) . '#timeline'">
                {{ __('timeline.action.load_more') }}
            </x-icon-btn>
        </div>
    @endif
</div>
