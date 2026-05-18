@extends('layouts.app')
@section('title', __('Materialien'))
@section('nav-title', __('Materialverbrauch'))

@section('content')
@php
    $num = fn (float $v, int $d = 2) => number_format($v, $d, ',', '.');
    $eur = fn (float $v) => number_format($v, 2, ',', '.') . ' €';
@endphp

<x-page-shell>

    <x-filter-bar :action="route('reports.materials')" :reset="route('reports.materials')">
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        @endif
        <x-slot:extra>
            <a href="{{ route('reports.materials', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="download" />CSV
            </a>
            <a href="{{ route('reports.materials', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="picture_as_pdf" />PDF
            </a>
        </x-slot:extra>
    </x-filter-bar>

    <div class="stats stats-vertical sm:stats-horizontal w-full rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div class="stat">
            <div class="stat-title">{{ __('Materialien') }}</div>
            <div class="stat-value text-2xl">{{ $totals['materials'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Verwendungen') }}</div>
            <div class="stat-value text-2xl">{{ $totals['usage_count'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Netto Σ') }}</div>
            <div class="stat-value text-2xl">{{ $eur($totals['line_total_net']) }}</div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Verbrauch pro Material') }}</h3>
        @if (empty($rows))
            <x-empty-state :title="__('Im Zeitraum wurden keine Materialien verbucht.')" />
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('SKU') }}</th>
                            <th>{{ __('Material') }}</th>
                            <th>{{ __('Einheit') }}</th>
                            <th class="text-right">{{ __('Menge') }}</th>
                            <th class="text-right">{{ __('Verwendungen') }}</th>
                            <th class="text-right">{{ __('Netto') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td class="font-mono text-xs">{{ $r['sku'] ?? '—' }}</td>
                                <td class="font-semibold">{{ $r['name'] }}</td>
                                <td>{{ $r['unit'] }}</td>
                                <td class="text-right tabular-nums">{{ $num($r['quantity'], 3) }}</td>
                                <td class="text-right tabular-nums">{{ $r['usage_count'] }}</td>
                                <td class="text-right tabular-nums">{{ $eur($r['line_total_net']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td colspan="4">{{ __('Gesamt') }}</td>
                            <td class="text-right tabular-nums">{{ $totals['usage_count'] }}</td>
                            <td class="text-right tabular-nums">{{ $eur($totals['line_total_net']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</x-page-shell>
@endsection
