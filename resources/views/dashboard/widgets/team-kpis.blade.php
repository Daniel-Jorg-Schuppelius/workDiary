<x-card :title="__('Team-Kennzahlen')">
    @if ($team)
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-box border border-base-300 p-3">
                <p class="text-xs text-base-content/60">{{ __('Offen (Team)') }}</p>
                <p class="text-2xl font-semibold">{{ $team['kpi']['open_entries'] ?? 0 }}</p>
            </div>
            <div class="rounded-box border border-base-300 p-3">
                <p class="text-xs text-base-content/60">{{ __('In Bearbeitung') }}</p>
                <p class="text-2xl font-semibold">{{ $team['kpi']['progress_entries'] ?? 0 }}</p>
            </div>
            <div class="rounded-box border border-base-300 p-3">
                <p class="text-xs text-base-content/60">{{ __('Heute archiviert') }}</p>
                <p class="text-2xl font-semibold">{{ $team['kpi']['archived_today'] ?? 0 }}</p>
            </div>
            <div class="rounded-box border border-base-300 p-3">
                <p class="text-xs text-base-content/60">{{ __('User') }}</p>
                <p class="text-2xl font-semibold">{{ $team['kpi']['user_count'] ?? 0 }}</p>
            </div>
        </div>
    @else
        <p class="text-sm text-base-content/60">{{ __('Keine Team-Daten verfügbar.') }}</p>
    @endif
</x-card>
