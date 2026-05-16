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
        return number_format($val, 2, ',', '.') . ' €';
    };
    $kindLabel = [
        'work' => __('Arbeit'),
        'travel' => __('Reise'),
        'standby' => __('Bereitschaft'),
    ];
    $kindBadge = [
        'work' => 'badge-primary',
        'travel' => 'badge-info',
        'standby' => 'badge-warning',
    ];
@endphp

<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">

    <x-filter-bar :action="route('reports.my-month')" :reset="route('reports.my-month')">
        <x-slot:extra>
            <a href="{{ route('reports.my-month', ['export' => 'csv']) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="download" />CSV
            </a>
            <a href="{{ route('reports.my-month', ['export' => 'pdf']) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="picture_as_pdf" />PDF
            </a>
        </x-slot:extra>
    </x-filter-bar>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <p class="text-xs text-base-content/60">
                {{ __('Tagesweise Übersicht aller eigenen Zeiteinträge im Monat.') }}
            </p>
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
            <div class="rounded-box border border-base-300 bg-base-200 p-6 text-center text-sm text-base-content/60">
                {{ __('Keine Zeiteinträge in diesem Monat.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Datum') }}</th>
                            <th>{{ __('Zeit') }}</th>
                            <th>{{ __('Art') }}</th>
                            <th>{{ __('Kunde / Projekt') }}</th>
                            <th>{{ __('Tätigkeit / Beschreibung') }}</th>
                            <th class="text-right">{{ __('Dauer') }}</th>
                            <th class="text-right">{{ __('Erlös') }}</th>
                        </tr>
                    </thead>
                    <tbody>
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
                                            {{ $e->started_at->format('H:i') }}–{{ $e->ended_at->format('H:i') }}
                                        @else
                                            <span class="text-base-content/40">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-sm {{ $kindBadge[$e->kind] ?? 'badge-ghost' }}">{{ $kindLabel[$e->kind] ?? $e->kind }}</span>
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
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td colspan="5">{{ __('Gesamt') }}</td>
                            <td class="text-right">{{ $fmt($monthMinutes) }}</td>
                            <td class="text-right">{{ $money($monthRate) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
