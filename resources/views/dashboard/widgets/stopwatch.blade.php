{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : stopwatch.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Stoppuhr" — Daten: StopwatchWidget.
--}}
<x-card :title="__('Stoppuhr')" icon="timer">
    @if ($entry)
        <div class="flex flex-wrap items-center justify-between gap-3"
             x-data="stopwatch('{{ $entry->started_at?->toIso8601String() }}')">
            <div class="min-w-0">
                <p class="font-['Space_Grotesk'] text-2xl font-bold tabular-nums text-primary" x-text="display">00:00:00</p>
                <p class="truncate text-sm text-muted">
                    {{ $entry->project?->name ?? __('Ohne Projekt') }}
                    @if ($entry->description)
                        · {{ \CommonToolkit\Helper\Data\StringHelper::truncate($entry->description, 60) }}
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('stopwatch.stop') }}" class="leading-none">
                @csrf
                <x-button type="submit" tone="error" size="sm" icon="stop_circle">{{ __('Stoppen') }}</x-button>
            </form>
        </div>
    @else
        <x-empty-state compact icon="timer_off"
                       :title="__('Keine laufende Zeit')" :message="__('Zeiten lassen sich im Stundenzettel oder direkt am Eintrag starten.')" />
    @endif
</x-card>
