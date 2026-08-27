{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : today-shifts.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Heute" — Daten: TodayShiftsWidget.
--}}
<x-card :title="__('Heute')" icon="today">
    @if ($shifts->isEmpty())
        <x-empty-state compact icon="event_available"
                       :title="__('Keine Schicht heute')" :message="__('Keine Schicht heute.')" />
    @else
        <ul class="space-y-2">
            @foreach ($shifts as $shift)
                <li class="flex items-center justify-between gap-3 rounded-box border border-base-300 bg-base-200 px-3 py-2 text-sm">
                    <span class="inline-flex items-center gap-1"><x-icon name="event" /> {{ $shift->start_at->ftime() }} – {{ $shift->end_at->ftime() }}</span>
                    <span class="text-muted">{{ $shift->note ? \CommonToolkit\Helper\Data\StringHelper::truncate($shift->note, 40) : '' }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
