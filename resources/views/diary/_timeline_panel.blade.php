{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _timeline_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Auftrags-Timeline „Verlauf" (MVP-010).
  Erwartet: $diary, $timelineItems (list<TimelineItem>), $timelineHasMore (bool),
            $timelineType (string, ''=alle), $timelineLimit (int)
--}}
<x-card as="section" id="timeline" :title="__('timeline.title.section')" icon="history">
    {{-- Filter-Chips nach Ereignistyp (serverseitig per Query-Param) --}}
    <nav class="mb-4 flex flex-wrap gap-1.5" aria-label="{{ __('timeline.filter.label') }}">
        <a href="{{ route('diary.show', $diary) }}#timeline"
           @class(['badge badge-sm', 'badge-primary' => $timelineType === '', 'badge-ghost' => $timelineType !== ''])>
            {{ __('timeline.filter.all') }}
        </a>
        @foreach (\App\Services\Timeline\DiaryEntryTimelineService::TYPES as $typeKey)
            <a href="{{ route('diary.show', [$diary, 'timeline_type' => $typeKey]) }}#timeline"
               @class(['badge badge-sm', 'badge-primary' => $timelineType === $typeKey, 'badge-ghost' => $timelineType !== $typeKey])>
                {{ __('timeline.filter.' . $typeKey) }}
            </a>
        @endforeach
    </nav>

    @include('timeline._items', ['items' => $timelineItems])

    @if ($timelineHasMore)
        <div class="mt-4 text-center">
            <x-icon-btn icon="expand_more" tone="outline" size="sm" show-label
                        :href="route('diary.show', array_filter(['diary' => $diary, 'timeline_type' => $timelineType ?: null, 'timeline_limit' => $timelineLimit + 50])) . '#timeline'">
                {{ __('timeline.action.load_more') }}
            </x-icon-btn>
        </div>
    @endif
</x-card>
