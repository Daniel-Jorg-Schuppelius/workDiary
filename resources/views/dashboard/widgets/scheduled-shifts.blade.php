{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : scheduled-shifts.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Nächste geplante Schichten" — Daten: ScheduledShiftsWidget.
--}}
<x-card :title="__('Nächste geplante Schichten')" icon="calendar_month">
    <x-slot:actions>
        <x-button href="{{ route('schedule.index') }}" tone="ghost" size="xs">{{ __('Alle →') }}</x-button>
    </x-slot:actions>

    @if ($shifts->isEmpty())
        <x-empty-state compact icon="calendar_month"
                       :title="__('Nichts geplant')" :message="__('Keine geplanten Schichten in den nächsten 7 Tagen.')" />
    @else
        <ul class="space-y-1.5 text-sm">
            @foreach ($shifts as $sshift)
                <li class="flex items-center gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-[0.65rem] font-bold text-white"
                          style="background:{{ $sshift->shiftType?->color ?? '#6b7280' }};">
                        {{ $sshift->shiftType?->abbreviation ?? '?' }}
                    </span>
                    <span class="font-medium">{{ \Carbon\Carbon::parse($sshift->date)->translatedFormat('D d.m.') }}</span>
                    @if ($sshift->resolvedStartTime())
                        <span class="text-muted">{{ $sshift->resolvedStartTime() }}{{ $sshift->resolvedEndTime() ? '–'.$sshift->resolvedEndTime() : '' }}</span>
                    @endif
                    @if ($sshift->shiftType)
                        <span class="ml-auto text-xs text-muted">{{ $sshift->shiftType->name }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
