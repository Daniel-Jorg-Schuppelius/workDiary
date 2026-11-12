{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _plan_ist.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Plan/Ist der Anwesenheit im Kopf des Tages (MVP-1552). Erwartet $planIst
  aus DayCloseService::context(); Tage ohne Soll zeigen nichts.
--}}
@if (($planIst ?? null) !== null && ! $planIst['no_plan'])
    @php($planIstDuration = static fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock'))
    <x-card padding="px-4 py-2" data-plan-ist>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <span class="text-xs uppercase tracking-[0.18em] text-muted">{{ __('day-close.plan_ist.label') }}</span>
            <span class="tabular-nums">
                {{ $planIst['plan_start'] !== null
                    ? __('day-close.plan_ist.plan', ['start' => $planIst['plan_start'], 'duration' => $planIstDuration($planIst['plan_minutes'])])
                    : __('day-close.plan_ist.plan_no_start', ['duration' => $planIstDuration($planIst['plan_minutes'])]) }}
            </span>
            <span class="text-muted">—</span>
            <span class="tabular-nums">
                {{ $planIst['actual_start'] !== null
                    ? __('day-close.plan_ist.actual', ['start' => $planIst['actual_start'], 'end' => $planIst['actual_end'] ?? '…', 'duration' => $planIstDuration($planIst['actual_minutes'])])
                    : __('day-close.plan_ist.actual_none') }}
            </span>
            @if ($planIst['actual_start'] !== null)
                @foreach ($planIst['warnings'] as $warning)
                    <x-status-badge tone="warning" size="xs">{{ __('reporting.plan_ist.warning.' . $warning, ['minutes' => \App\Services\Reporting\PlanIstReportBuilder::lateStartMinutes(), 'percent' => \App\Services\Reporting\PlanIstReportBuilder::hoursDiffPercent()]) }}</x-status-badge>
                @endforeach
            @endif
        </div>
    </x-card>
@endif
