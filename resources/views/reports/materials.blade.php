@extends('layouts.app')
@section('title', __('Materialien'))
@section('nav-title', __('Materialverbrauch'))

@section('content')
@php
    $num = fn (float $v, int $d = 2) => number_format($v, $d, ',', '.');
    $eur = fn (float $v) => number_format($v, 2, ',', '.') . ' €';
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Verbrauch und Netto-Erlöse je Material im Zeitraum.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.materials', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.materials', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($isAdmin)
        <x-filter-bar :action="route('reports.materials')" :reset="route('reports.materials')">
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>
    @endif

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Materialien') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['materials'] }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Verwendungen') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['usage_count'] }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Netto Σ') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $eur($totals['line_total_net']) }}</div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Verbrauch pro Material') }}</h3>
        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>' :title="__('Im Zeitraum wurden keine Materialien verbucht.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('SKU') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Material') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Einheit') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Menge') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Verwendungen') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Netto') }}</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td colspan="4">{{ __('Gesamt') }}</td>
                        <td class="text-right tabular-nums">{{ $totals['usage_count'] }}</td>
                        <td class="text-right tabular-nums">{{ $eur($totals['line_total_net']) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($rows as $r)
                    <tr>
                        <td class="font-mono text-xs">{{ $r['sku'] ?? '—' }}</td>
                        <td class="font-semibold">{{ $r['name'] }}</td>
                        <td>{{ $r['unit'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $r['quantity'] }}">{{ $num($r['quantity'], 3) }}</td>
                        <td class="text-right tabular-nums">{{ $r['usage_count'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $r['line_total_net'] }}">{{ $eur($r['line_total_net']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</x-page-shell>
@endsection
