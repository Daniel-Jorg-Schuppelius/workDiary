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

<x-page-shell>

    <x-filter-bar :action="route('reports.week-by-user')" :reset="route('reports.week-by-user')">
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur meine') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        @endif
        <x-slot:extra>
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('reports.week-by-user', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv']))"
                        show-label>CSV</x-icon-btn>
            <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                        :href="route('reports.week-by-user', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf']))"
                        show-label>PDF</x-icon-btn>
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
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">view_week</span>' :title="__('Keine Einträge in dieser Woche.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                        @foreach ($dayLabels as $label)
                            <x-table.th sort type="duration" align="right">{{ $label }}</x-table.th>
                        @endforeach
                        <x-table.th sort type="duration" align="right">Σ {{ __('Stunden') }}</x-table.th>
                        <x-table.th sort type="number" align="right">Σ €</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>Σ {{ __('Tag') }}</td>
                        @foreach ($dayTotals as $m)
                            <td class="text-right">{{ $fmt($m) }}</td>
                        @endforeach
                        <td class="text-right">{{ $fmt($weekTotal) }}</td>
                        <td class="text-right">{{ $money($weekRate) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($byUser as $uid => $row)
                    <tr>
                        <td class="font-medium">{{ $users->get($uid)?->name ?? '#' . $uid }}</td>
                        @foreach ($row['days'] as $minutes)
                            <td class="text-right text-sm @if ($minutes === 0) opacity-30 @endif" data-sort-value="{{ (int) $minutes }}">{{ $fmt($minutes) }}</td>
                        @endforeach
                        <td class="text-right font-semibold" data-sort-value="{{ (int) $row['total'] }}">{{ $fmt($row['total']) }}</td>
                        <td class="text-right" data-sort-value="{{ (float) $row['rate'] }}">{{ $money($row['rate']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</x-page-shell>
@endsection
