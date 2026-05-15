@extends('layouts.app')
@section('title', __('Woche pro Mitarbeiter'))
@section('nav-title', __('Woche pro Mitarbeiter'))

@section('content')
@php
    $fmt = function (int $min): string {
        if ($min <= 0) return '–';
        return intdiv($min, 60) . ':' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT);
    };
    $money = function (float $val): string {
        return number_format($val, 2, ',', '.') . ' €';
    };
@endphp

<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">

    <x-filter-bar :action="route('reports.week-by-user')" :reset="route('reports.week-by-user')">
        <x-filter-field :label="__('Jahr')" for="rep-year">
            <input id="rep-year" type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="input input-sm input-bordered w-24">
        </x-filter-field>
        <x-filter-field :label="__('KW')" for="rep-week">
            <input id="rep-week" type="number" name="week" value="{{ $week }}" min="1" max="53" class="input input-sm input-bordered w-20">
        </x-filter-field>
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur meine') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        @endif
        <x-slot:extra>
            <a href="{{ route('reports.week-by-user', ['year' => $prevYear, 'week' => $prevWeek, 'scope' => $isAdmin ? $scope : null]) }}" class="btn btn-sm btn-ghost">‹</a>
            <a href="{{ route('reports.week-by-user', ['year' => $nextYear, 'week' => $nextWeek, 'scope' => $isAdmin ? $scope : null]) }}" class="btn btn-sm btn-ghost">›</a>
            <a href="{{ route('reports.week-by-user', array_filter(['year' => $year, 'week' => $week, 'scope' => $isAdmin ? $scope : null, 'export' => 'csv'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="download" />CSV
            </a>
            <a href="{{ route('reports.week-by-user', array_filter(['year' => $year, 'week' => $week, 'scope' => $isAdmin ? $scope : null, 'export' => 'pdf'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="picture_as_pdf" />PDF
            </a>
        </x-slot:extra>
    </x-filter-bar>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ $weekLabel }}</h2>
            <div class="flex items-baseline gap-4">
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ Std.</span>
                    <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $weekTotal > 0 ? 'text-primary' : 'text-base-content/50' }}">
                        {{ $fmt($weekTotal) }}
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ €</span>
                    <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $weekRate > 0 ? 'text-primary' : 'text-base-content/50' }}">
                        {{ $money($weekRate) }}
                    </span>
                </div>
            </div>
        </div>

        @if (count($byUser) === 0)
            <div class="rounded-box border border-base-300 bg-base-200 p-6 text-center text-sm text-base-content/60">
                {{ __('Keine Einträge in dieser Woche.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Mitarbeiter') }}</th>
                            @foreach ($dayLabels as $label)
                                <th class="text-right">{{ $label }}</th>
                            @endforeach
                            <th class="text-right">Σ {{ __('Stunden') }}</th>
                            <th class="text-right">Σ €</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byUser as $uid => $row)
                            <tr>
                                <td class="font-medium">{{ $users->get($uid)?->name ?? '#' . $uid }}</td>
                                @foreach ($row['days'] as $minutes)
                                    <td class="text-right text-sm @if ($minutes === 0) opacity-30 @endif">{{ $fmt($minutes) }}</td>
                                @endforeach
                                <td class="text-right font-semibold">{{ $fmt($row['total']) }}</td>
                                <td class="text-right">{{ $money($row['rate']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td>Σ {{ __('Tag') }}</td>
                            @foreach ($dayTotals as $m)
                                <td class="text-right">{{ $fmt($m) }}</td>
                            @endforeach
                            <td class="text-right">{{ $fmt($weekTotal) }}</td>
                            <td class="text-right">{{ $money($weekRate) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
