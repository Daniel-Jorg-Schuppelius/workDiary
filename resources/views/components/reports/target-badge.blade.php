{{--
  Feature 002 (Zielwerte): kompakte Soll/Ist-Anzeige mit Ampel-Tone.
  $eval = Rückgabe von ReportTargetEvaluator::evaluate() oder null.
--}}
@props([
    'eval' => null,
    'unit' => '%',
    'compact' => false,
])

@php
    /** @var array{target: float, actual: float|null, deviation: float|null, met: bool|null, tone: string, note: string|null}|null $eval */
    $fmt = fn($v): string => $v === null ? '–' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2, withThousandsSeparator: true) . $unit;
@endphp

@if ($eval === null)
    <span class="text-base-content/40 text-xs">{{ __('reporting.target.no_target') }}</span>
@else
    @php
        $toneMap = ['success' => 'success', 'warning' => 'warning', 'error' => 'error', 'neutral' => 'ghost'];
        $tone = $toneMap[$eval['tone']] ?? 'ghost';
        $dev = $eval['deviation'];
        $devStr = $dev === null ? '' : (($dev > 0 ? '+' : '') . \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $dev, 2, withThousandsSeparator: true) . $unit);
    @endphp
    <span class="inline-flex items-center gap-1.5 whitespace-nowrap" @if($eval['note']) title="{{ $eval['note'] }}" @endif>
        <x-status-badge :tone="$tone" size="xs">
            {{ __('reporting.target.soll') }} {{ $fmt($eval['target']) }}
        </x-status-badge>
        @unless ($compact)
            <span class="text-xs text-base-content/70">{{ __('reporting.target.ist') }} {{ $fmt($eval['actual']) }}</span>
        @endunless
        @if ($devStr !== '')
            <span class="text-xs tabular-nums {{ $eval['met'] ? 'text-success' : ($tone === 'warning' ? 'text-warning' : 'text-error') }}">{{ $devStr }}</span>
        @endif
    </span>
@endif
