{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : vacation-flex.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Urlaub & Flex" — Daten: VacationFlexWidget.
--}}
<x-card :title="__('Urlaub & Flex')" icon="beach_access">
    <x-slot:actions>
        <x-button href="{{ route('vacations.index') }}" tone="ghost" size="xs">{{ __('Alle →') }}</x-button>
    </x-slot:actions>

    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-box border border-info/40 bg-info/5 px-4 py-3">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Anträge offen') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums text-info">{{ $vacation['pending'] ?? 0 }}</p>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 px-4 py-3 shadow-xs">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Genehmigt') }} ({{ $now->year }})</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums">
                {{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) ($vacation['approved_days_this_year'] ?? 0), 1, withThousandsSeparator: true), '0'), ',') }}
                <span class="text-sm font-normal text-muted">{{ __('Tage') }}</span>
            </p>
        </div>
    </div>
</x-card>
