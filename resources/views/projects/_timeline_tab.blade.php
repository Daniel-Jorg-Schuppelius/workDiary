{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _timeline_tab.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Projekt-Timeline (MVP-037, Rang 56): modulübergreifende Fallakte je Projekt —
  Aufträge, Meilensteine, Dokumente, Kommunikationsnotizen (visibleTo-gefiltert),
  Service-Tickets. Read-only mit Offset-Pagination.
--}}
<x-card :title="__('Timeline')" icon="timeline" :count="count($timeline)">
    @if ($timeline === [])
        <p class="text-sm text-base-content/60">{{ __('Noch keine Ereignisse zu diesem Projekt.') }}</p>
    @else
        <ul class="divide-y divide-base-300 text-sm">
            @foreach ($timeline as $item)
                <li class="flex items-start justify-between gap-3 py-2">
                    <div class="flex min-w-0 items-start gap-2">
                        <span class="material-symbols-outlined text-base text-base-content/60" aria-hidden="true">{{ $item->icon }}</span>
                        <div class="min-w-0">
                            @if ($item->url !== null)
                                <a class="link link-hover font-medium" href="{{ $item->url }}">{{ $item->title }}</a>
                            @else
                                <span class="font-medium">{{ $item->title }}</span>
                            @endif
                            @if ($item->summary !== null)
                                <div class="text-base-content/60">{{ $item->summary }}</div>
                            @endif
                            @if ($item->actor !== null)
                                <div class="text-xs text-base-content/50">{{ $item->actor }}</div>
                            @endif
                        </div>
                    </div>
                    <span class="shrink-0 tabular-nums text-base-content/60">{{ $item->occurredAt?->fdate() ?? '—' }}</span>
                </li>
            @endforeach
        </ul>
        @if ($timelineHasMore)
            <div class="mt-3 text-center">
                <a class="btn btn-ghost btn-sm" href="{{ route('projects.show', ['project' => $project, 'tab' => 'timeline', 'toffset' => $timelineOffset + 50]) }}">
                    {{ __('Ältere Ereignisse anzeigen') }}
                </a>
            </div>
        @endif
    @endif
</x-card>
