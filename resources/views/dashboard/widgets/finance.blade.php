{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : finance.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Finanzen & Reisen" — Daten: FinanceWidget.
--}}
@php
    $month = $finance['month'] ?? [];
    $vacationData = $finance['vacation'] ?? [];
    $approver = $finance['approver_pending'] ?? null;
@endphp
<x-card :title="__('Finanzen & Reisen')" icon="payments"
        :subtitle="__('Monat') . ' · ' . ($month['label'] ?? '')">
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 px-4 py-3 shadow-xs">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Spesen eingereicht (Brutto)') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums">
                {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) ($month['expenses_submitted_gross'] ?? 0), 2, withThousandsSeparator: true) }} €
            </p>
        </div>
        <div class="rounded-box border border-success/40 bg-success/5 px-4 py-3">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Davon erstattet') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums text-success">
                {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) ($month['expenses_reimbursed_gross'] ?? 0), 2, withThousandsSeparator: true) }} €
            </p>
        </div>
        <div class="rounded-box border border-warning/40 bg-warning/5 px-4 py-3">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Spesen ausstehend / Entwurf') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold">
                <span class="text-warning">{{ $month['expenses_pending_count'] ?? 0 }}</span>
                <span class="text-muted text-base font-normal">/</span>
                <span class="opacity-70">{{ $month['expenses_draft_count'] ?? 0 }}</span>
            </p>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 px-4 py-3 shadow-xs">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Reisen (Monat) / Entwürfe') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold">
                {{ $month['trips_count'] ?? 0 }}
                <span class="text-muted text-base font-normal">/</span>
                <span class="opacity-70">{{ $month['trip_drafts'] ?? 0 }}</span>
            </p>
        </div>
    </div>

    @if (! empty($approver))
        <div class="mt-3 rounded-box border border-error/40 bg-error/5 px-4 py-3">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Genehmigungs-Stack (gesamt)') }}</p>
            <p class="mt-1 flex items-center gap-4 font-['Space_Grotesk'] text-xl font-bold">
                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="receipt_long" class="text-base" />
                    <span>{{ $approver['expenses'] }}</span>
                    <a href="{{ route('expense-approvals.inbox') }}" class="link link-hover text-xs opacity-70">{{ __('Spesen') }}</a>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="beach_access" class="text-base" />
                    <span>{{ $approver['vacations'] }}</span>
                    <a href="{{ route('vacations.index') }}" class="link link-hover text-xs opacity-70">{{ __('Urlaub') }}</a>
                </span>
            </p>
        </div>
    @endif
</x-card>
