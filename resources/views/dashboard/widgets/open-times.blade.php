{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : open-times.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Offene Zeiten" — Daten: OpenTimesWidget.
--}}
<x-card :title="__('Offene Zeiten')" icon="hourglass_bottom">
    <x-slot:actions>
        <x-button href="{{ route('finance.open-times.index') }}" tone="ghost" size="xs">{{ __('Arbeitsliste →') }}</x-button>
    </x-slot:actions>

    <div class="grid grid-cols-2 gap-3">
        <div class="rounded-box border border-base-300 bg-base-100 px-4 py-3 shadow-xs">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Nicht abgerechnet') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums">{{ \App\Support\Formats::duration($minutes) }}</p>
            <p class="text-xs text-muted">{{ __(':n Zeiteinträge', ['n' => $count]) }}</p>
        </div>
        <div class="rounded-box border {{ $staleCount > 0 ? 'border-warning/40 bg-warning/5' : 'border-base-300 bg-base-200' }} px-4 py-3">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Älter als :d Tage', ['d' => $staleAfterDays]) }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums {{ $staleCount > 0 ? 'text-warning' : '' }}">{{ $staleCount }}</p>
        </div>
    </div>
</x-card>
