@extends('layouts.app')
@section('title', __('Mein Jahr') . ' ' . $year)
@section('nav-title', __('Mein Jahr') . ' ' . $year)

@section('content')
@php
    $fmt = function (int $min): string {
        if ($min <= 0) {
            return '';
        }
        return intdiv($min, 60) . ':' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT);
    };
    $fmtTotal = function (int $min): string {
        $sign = $min < 0 ? '-' : '';
        $abs = abs($min);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
    $intensity = function (int $min) use ($maxCell): string {
        if ($min <= 0 || $maxCell <= 0) {
            return '';
        }
        // Skala 8% .. 60% Primary-Tönung.
        $ratio = min(1.0, $min / $maxCell);
        $alpha = 8 + (int) round($ratio * 52);
        return 'background-color: color-mix(in oklab, var(--color-primary) ' . $alpha . '%, transparent);';
    };
@endphp

<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">

    <x-filter-bar :action="route('reports.my-year')" :reset="route('reports.my-year')">
        <x-filter-field :label="__('Jahr')" for="rep-year">
            <input id="rep-year" type="number" name="year" value="{{ $year }}" min="2000" max="2100"
                   class="input input-sm input-bordered w-24">
        </x-filter-field>
        <x-filter-field :label="__('Art')" for="rep-kind">
            <select id="rep-kind" name="kind" class="select select-sm select-bordered" onchange="this.form.submit()">
                <option value="all" @selected($kind === 'all')>{{ __('Alle') }}</option>
                <option value="work" @selected($kind === 'work')>{{ __('Arbeit') }}</option>
                <option value="travel" @selected($kind === 'travel')>{{ __('Reise') }}</option>
                <option value="standby" @selected($kind === 'standby')>{{ __('Bereitschaft') }}</option>
            </select>
        </x-filter-field>
        <x-slot:extra>
            <a href="{{ route('reports.my-year', array_filter(['year' => $prevYear, 'kind' => $kind === 'all' ? null : $kind])) }}"
               class="btn btn-sm btn-ghost gap-1" title="{{ __('Vorheriges Jahr') }}">
                <x-icon name="chevron_left" />
                <span>{{ $prevYear }}</span>
            </a>
            <a href="{{ route('reports.my-year', array_filter(['year' => $nextYear, 'kind' => $kind === 'all' ? null : $kind])) }}"
               class="btn btn-sm btn-ghost gap-1" title="{{ __('Nächstes Jahr') }}">
                <span>{{ $nextYear }}</span>
                <x-icon name="chevron_right" />
            </a>
        </x-slot:extra>
    </x-filter-bar>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-base-content/60">
                {{ __('Stunden pro Tag und Monat — Färbung skaliert mit dem höchsten Tageswert des Jahres.') }}
            </p>
            <div class="flex items-baseline gap-2">
                <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ __('Jahressumme') }}</span>
                <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $yearTotal > 0 ? 'text-primary' : 'text-base-content/50' }}">
                    {{ $fmtTotal($yearTotal) }}
                </span>
            </div>
        </div>

        @if ($yearTotal === 0)
            <div class="rounded-box border border-dashed border-base-300 px-4 py-10 text-center text-sm text-base-content/60">
                {{ __('Keine Zeiteinträge für dieses Jahr.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-xs w-full text-center tabular-nums">
                    <thead>
                        <tr>
                            <th class="text-left font-semibold uppercase tracking-[0.12em] text-[0.65rem] text-base-content/60">{{ __('Monat') }}</th>
                            @for ($d = 1; $d <= 31; $d++)
                                <th class="px-1 font-semibold text-[0.65rem] text-base-content/50">{{ $d }}</th>
                            @endfor
                            <th class="bg-base-200 px-2 font-semibold uppercase tracking-[0.12em] text-[0.65rem] text-base-content/70">Σ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($m = 1; $m <= 12; $m++)
                            <tr>
                                <th class="text-left font-semibold text-base-content/80 whitespace-nowrap">{{ $monthNames[$m] }}</th>
                                @for ($d = 1; $d <= 31; $d++)
                                    @if ($d > $daysInMonth[$m])
                                        <td class="bg-base-200/40 text-base-content/30">·</td>
                                    @else
                                        @php $val = $matrix[$m][$d]; @endphp
                                        <td class="text-[0.65rem]" style="{{ $intensity($val) }}"
                                            title="{{ sprintf('%02d.%02d.%d', $d, $m, $year) }} — {{ $val > 0 ? $fmtTotal($val) : __('keine Einträge') }}">
                                            {{ $fmt($val) }}
                                        </td>
                                    @endif
                                @endfor
                                <td class="bg-base-200 font-semibold text-base-content {{ $monthTotals[$m] > 0 ? '' : 'text-base-content/40' }}">
                                    {{ $monthTotals[$m] > 0 ? $fmt($monthTotals[$m]) : '·' }}
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="bg-base-200 text-left text-[0.65rem] uppercase tracking-[0.12em] text-base-content/70">Σ</th>
                            @for ($d = 1; $d <= 31; $d++)
                                <th class="bg-base-200 text-[0.65rem] {{ $dayTotals[$d] > 0 ? 'text-base-content' : 'text-base-content/40' }}">
                                    {{ $dayTotals[$d] > 0 ? $fmt($dayTotals[$d]) : '' }}
                                </th>
                            @endfor
                            <th class="bg-primary/10 font-semibold text-primary">{{ $fmt($yearTotal) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
