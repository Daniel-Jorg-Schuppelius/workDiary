{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _deviation.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Abweichung am Schritt mit Folgemaßnahme und Risikoakzeptanz (MVP-1567). Variablen: $deviation, $sr, $run, $runActive, $blocksCompletion. --}}
@php
    /** @var \App\Models\Procedure\ProcedureDeviation $deviation */
    $severityTone = match ($deviation->severity) {
        \App\Enums\Procedure\ProcedureDeviationSeverity::Critical => 'error',
        \App\Enums\Procedure\ProcedureDeviationSeverity::High => 'warning',
        default => 'neutral',
    };
@endphp
<x-card padding="px-3 py-2" class="mt-3 space-y-2" data-deviation>
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <x-icon name="alert" class="text-muted" />
        <span class="font-medium">{{ $deviation->deviation_type->label() }}</span>
        <x-status-badge :tone="$severityTone" size="xs">{{ $deviation->severity->label() }}</x-status-badge>
        @if ($deviation->risk_accepted_at !== null)
            <x-status-badge tone="success" size="xs">{{ __('procedure.deviation.risk_accepted', ['name' => $deviation->riskAcceptedBy?->name ?? '—', 'date' => $deviation->risk_accepted_at->fdatetime()]) }}</x-status-badge>
        @elseif ($blocksCompletion)
            <x-status-badge tone="error" size="xs">{{ __('procedure.deviation.risk_open') }}</x-status-badge>
        @endif
    </div>
    <p class="whitespace-pre-wrap text-sm text-base-content/70">{{ $deviation->reason_text }}</p>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
        @if ($deviation->proposed_action !== null)
            <span>{{ __('procedure.run.deviationAction') }}: {{ $deviation->proposed_action->label() }}</span>
        @endif
        @if ($deviation->open_issue_id !== null)
            <span>{{ __('procedure.report.follow_up.open_issue') }} #{{ $deviation->open_issue_id }}</span>
        @endif
        @if ($deviation->follow_up_diary_entry_id !== null)
            <span>{{ __('procedure.report.follow_up.diary_entry') }}</span>
        @endif
        @if ($deviation->requalifyRun !== null)
            <a class="link" href="{{ route('procedure-runs.show', $deviation->requalifyRun) }}">{{ __('procedure.report.follow_up.requalify_run') }}</a>
        @endif
    </div>

    @if ($runActive && $blocksCompletion)
        @can('acceptRisk', $deviation)
            <details class="text-xs">
                <summary class="cursor-pointer text-muted">{{ __('procedure.deviation.accept_risk') }}</summary>
                <form method="POST" action="{{ route('procedure-runs.steps.deviation.accept-risk', [$run, $sr]) }}" class="mt-2 space-y-2">
                    @csrf
                    <p class="text-muted">{{ __('procedure.deviation.accept_risk_hint') }}</p>
                    <textarea aria-label="{{ __('procedure.deviation.accept_risk_note') }}" name="note" rows="2" minlength="5" maxlength="2000" required
                              class="textarea textarea-bordered textarea-sm w-full"
                              placeholder="{{ __('procedure.deviation.accept_risk_note') }}"></textarea>
                    <x-button type="submit" tone="warning" size="xs">{{ __('procedure.deviation.accept_risk_confirm') }}</x-button>
                </form>
            </details>
        @else
            <p class="text-xs text-muted">{{ __('procedure.deviation.accept_risk_denied') }}</p>
        @endcan
    @endif
</x-card>
