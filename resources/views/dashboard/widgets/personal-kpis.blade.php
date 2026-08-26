{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : personal-kpis.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-card :title="__('Persönliche Kennzahlen')">
    @if (! empty($personal))
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-box border border-base-300 p-3">
                <p class="text-xs text-muted">{{ __('Offene Einträge') }}</p>
                <p class="text-2xl font-semibold">{{ $personal['open_entries'] ?? 0 }}</p>
            </div>
            <div class="rounded-box border border-base-300 p-3">
                <p class="text-xs text-muted">{{ __('In Bearbeitung') }}</p>
                <p class="text-2xl font-semibold">{{ $personal['in_progress_entries'] ?? 0 }}</p>
            </div>
            <div class="rounded-box border border-base-300 p-3">
                <p class="text-xs text-muted">{{ __('Anstehende Schichten') }}</p>
                <p class="text-2xl font-semibold">{{ ($personal['upcoming_shifts'] ?? collect())->count() }}</p>
            </div>
            <div class="rounded-box border border-base-300 p-3">
                <p class="text-xs text-muted">{{ __('Offene Ausgaben') }}</p>
                <p class="text-2xl font-semibold">{{ $personal['open_expenses'] ?? 0 }}</p>
            </div>
        </div>
    @else
        <p class="text-sm text-muted">{{ __('Keine Daten verfügbar.') }}</p>
    @endif
</x-card>
