<x-card :title="__('Urlaub & Flex')">
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-box border border-base-300 p-3">
            <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Anträge offen') }}</p>
            <p class="mt-1 text-2xl font-semibold text-info">{{ $vacation['pending'] ?? 0 }}</p>
        </div>
        <div class="rounded-box border border-base-300 p-3">
            <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Genehmigt') }} ({{ now()->year }})</p>
            <p class="mt-1 text-2xl font-semibold">
                {{ rtrim(rtrim(number_format((float) ($vacation['approved_days_this_year'] ?? 0), 1, ',', '.'), '0'), ',') }}
                <span class="text-sm font-normal text-base-content/60">{{ __('Tage') }}</span>
            </p>
        </div>
    </div>
    <p class="mt-3 text-xs text-base-content/60">
        <a href="{{ route('vacations.index') }}" class="link link-hover">{{ __('Zur Urlaubsverwaltung') }}</a>
    </p>
</x-card>
