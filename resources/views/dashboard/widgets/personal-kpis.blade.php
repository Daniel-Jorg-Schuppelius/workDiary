<x-card :title="__('Persönliche Kennzahlen')">
    @if (! empty($personal))
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-box border border-base-300 p-3">
                <p class="text-xs text-base-content/60">{{ __('Offene Einträge') }}</p>
                <p class="text-2xl font-semibold">{{ $personal['open_entries'] ?? 0 }}</p>
            </div>
            <div class="rounded-box border border-base-300 p-3">
                <p class="text-xs text-base-content/60">{{ __('In Bearbeitung') }}</p>
                <p class="text-2xl font-semibold">{{ $personal['in_progress_entries'] ?? 0 }}</p>
            </div>
            <div class="rounded-box border border-base-300 p-3">
                <p class="text-xs text-base-content/60">{{ __('Anstehende Schichten') }}</p>
                <p class="text-2xl font-semibold">{{ ($personal['upcoming_shifts'] ?? collect())->count() }}</p>
            </div>
            <div class="rounded-box border border-base-300 p-3">
                <p class="text-xs text-base-content/60">{{ __('Offene Ausgaben') }}</p>
                <p class="text-2xl font-semibold">{{ $personal['open_expenses'] ?? 0 }}</p>
            </div>
        </div>
    @else
        <p class="text-sm text-base-content/60">{{ __('Keine Daten verfügbar.') }}</p>
    @endif
</x-card>
