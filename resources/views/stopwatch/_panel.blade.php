{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Stoppuhr-Panel — erwartet: $current (TimeEntry|null) --}}
<x-card>
    <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Stoppuhr') }}</h2>
    @if($current)
        <div class="mt-2">
            <div class="text-2xl font-bold tabular-nums" x-data="stopwatch('{{ $current->started_at?->toIso8601String() }}')">
                <span x-text="display"></span>
            </div>
            <div class="mt-1 text-xs text-base-content/60">{{ $current->description ?: __('Läuft…') }}</div>
            <form method="POST" action="{{ route('stopwatch.stop') }}" class="mt-2">
                @csrf
                <x-button tone="error" size="sm">{{ __('Stoppen') }}</x-button>
            </form>
        </div>
    @else
        <div class="mt-2 text-sm text-base-content/60">{{ __('Keine laufende Erfassung.') }}</div>
    @endif
</x-card>
