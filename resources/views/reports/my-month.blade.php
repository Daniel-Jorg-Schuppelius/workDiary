@extends('layouts.app')
@section('title', __('Mein Monat') . ' — ' . $monthLabel)
@section('nav-title', __('Mein Monat') . ' — ' . $monthLabel)

@section('content')
@php
    $fmt = function (int $min): string {
        $sign = $min < 0 ? '-' : '';
        $abs = abs($min);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
    $money = function (float $val): string {
        return \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($val, 2, withThousandsSeparator: true) . ' €';
    };
    $kindBadge = [
        'work' => 'badge-primary',
        'travel' => 'badge-info',
        'standby' => 'badge-warning',
    ];
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Tagesweise Übersicht aller eigenen Zeiteinträge im Monat.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.my-month', ['export' => 'csv'])"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_chart" tone="outline" size="sm"
                            :href="route('reports.my-month', ['export' => 'xlsx'])"
                            show-label>XLSX</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.my-month', ['export' => 'pdf'])"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <div class="mb-3 flex flex-wrap items-baseline justify-end gap-2">
            <div class="flex items-baseline gap-4">
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ Std.</span>
                    <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $monthMinutes > 0 ? 'text-primary' : 'text-base-content/50' }}">{{ $fmt($monthMinutes) }}</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ €</span>
                    <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $monthRate > 0 ? 'text-primary' : 'text-base-content/50' }}">{{ $money($monthRate) }}</span>
                </div>
            </div>
        </div>

        @if (empty($byDay))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>' :title="__('Keine Zeiteinträge in diesem Monat.')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Datum') }}</th>
                        <th>{{ __('Zeit') }}</th>
                        <th>{{ __('Art') }}</th>
                        <th>{{ __('Kunde / Projekt') }}</th>
                        <th>{{ __('Tätigkeit / Beschreibung') }}</th>
                        <th class="text-right">{{ __('Dauer') }}</th>
                        <th class="text-right">{{ __('Erlös') }}</th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td colspan="5">{{ __('Gesamt') }}</td>
                        <td class="text-right">{{ $fmt($monthMinutes) }}</td>
                        <td class="text-right">{{ $money($monthRate) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($byDay as $day => $info)
                    @php
                        $d = \Carbon\Carbon::parse($day)->locale(app()->getLocale());
                        $dayLabel = $d->isoFormat('dd, DD.MM.');
                        $isSunday = $d->isSunday();
                        $sundayCls = $isSunday ? ' text-error' : '';
                    @endphp
                    <tr class="bg-base-200/60{{ $sundayCls }}">
                        <th class="font-semibold text-base-content{{ $sundayCls }}">{{ $dayLabel }}</th>
                        <th colspan="4"></th>
                        <th class="text-right font-semibold tabular-nums text-base-content{{ $sundayCls }}">{{ $fmt($info['minutes']) }}</th>
                        <th class="text-right font-semibold tabular-nums text-base-content{{ $sundayCls }}">{{ $money($info['rate']) }}</th>
                    </tr>
                    @foreach ($info['entries'] as $e)
                        <tr class="{{ $sundayCls }}">
                            <td></td>
                            <td class="tabular-nums text-sm">
                                @if ($e->started_at && $e->ended_at)
                                    {{ $e->started_at->ftime() }}–{{ $e->ended_at->ftime() }}
                                @else
                                    <span class="text-base-content/40">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-sm {{ $kindBadge[$e->kind->value] ?? 'badge-ghost' }}">{{ $e->kind->label() }}</span>
                            </td>
                            <td class="text-sm">
                                @if ($e->project)
                                    @if ($e->project->customer)
                                        <span class="text-base-content/60">{{ $e->project->customer->name }} ·</span>
                                    @endif
                                    @if ($e->project->color)
                                        <span class="mr-1 inline-block size-2 rounded-full align-middle" style="background-color: {{ $e->project->color }};"></span>
                                    @endif
                                    {{ $e->project->name }}
                                @endif
                            </td>
                            <td class="text-sm text-base-content/80">
                                @if ($e->task)
                                    <span class="font-medium">{{ $e->task->title }}</span>
                                    @if ($e->description)<br>@endif
                                @endif
                                @if ($e->description)
                                    <span class="text-base-content/70">{{ $e->description }}</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ $fmt((int) $e->minutes) }}</td>
                            <td class="text-right tabular-nums">{{ $money((float) $e->rate) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
