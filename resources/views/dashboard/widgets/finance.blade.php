@php
    $month = $finance['month'] ?? [];
    $vacation = $finance['vacation'] ?? [];
    $approver = $finance['approver_pending'] ?? null;
@endphp
<x-card :title="__('Finanzen & Reisen') . ' · ' . ($month['label'] ?? '')">
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-box border border-base-300 p-3">
            <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Spesen eingereicht') }}</p>
            <p class="mt-1 text-xl font-semibold">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) ($month['expenses_submitted_gross'] ?? 0), 2, withThousandsSeparator: true) }} €</p>
            <p class="mt-1 text-xs text-base-content/60">
                {{ __('Erstattet') }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) ($month['expenses_reimbursed_gross'] ?? 0), 2, withThousandsSeparator: true) }} €
            </p>
        </div>
        <div class="rounded-box border border-base-300 p-3">
            <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Status') }}</p>
            <p class="mt-1 text-sm">
                <span class="text-warning font-semibold">{{ $month['expenses_pending_count'] ?? 0 }}</span>
                {{ __('offen') }}
                ·
                <span class="opacity-70">{{ $month['expenses_draft_count'] ?? 0 }}</span>
                {{ __('Entwurf') }}
            </p>
            <p class="mt-1 text-xs text-base-content/60">
                {{ __('Reisen') }}: {{ $month['trips_count'] ?? 0 }} ({{ $month['trip_drafts'] ?? 0 }} {{ __('Entwurf') }})
            </p>
        </div>
    </div>

    <div class="mt-3 rounded-box border border-base-300 p-3">
        <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Urlaub') }}</p>
        <p class="mt-1 text-sm">
            <span class="text-info font-semibold">{{ $vacation['pending'] ?? 0 }}</span> {{ __('Anträge offen') }}
            ·
            <span class="opacity-70">{{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) ($vacation['approved_days_this_year'] ?? 0), 1, withThousandsSeparator: true), '0'), ',') }}</span> {{ __('Tage genehmigt') }}
        </p>
    </div>

    @if (! empty($approver))
        <div class="mt-3 rounded-box border border-warning/40 bg-warning/5 p-3">
            <p class="text-xs uppercase tracking-wider text-warning">{{ __('Genehmigungen ausstehend') }}</p>
            <p class="mt-1 text-sm">
                <a href="{{ route('expenses.index', ['status' => 'pending']) }}" class="link link-warning">
                    {{ $approver['expenses'] }} {{ __('Spesen') }}
                </a>
                ·
                <a href="{{ route('vacations.index', ['status' => 'pending']) }}" class="link link-warning">
                    {{ $approver['vacations'] }} {{ __('Urlaube') }}
                </a>
            </p>
        </div>
    @endif
</x-card>
