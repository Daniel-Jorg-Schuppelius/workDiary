{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : upcoming-shifts.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Nächste Schichten" — Daten: UpcomingShiftsWidget.
--}}
<x-card :title="__('Nächste Schichten')" icon="event_upcoming">
    @if ($shifts->isEmpty())
        <x-empty-state compact icon="event_busy"
                       :title="__('Keine geplanten Schichten')" :message="__('Keine geplanten Schichten.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($shifts as $shift)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <span>{{ $shift->start_at->format('d.m. H:i') }} – {{ $shift->end_at->format('d.m. H:i') }}</span>
                    @if ($shift->note)<span class="text-muted">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($shift->note, 50) }}</span>@endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
