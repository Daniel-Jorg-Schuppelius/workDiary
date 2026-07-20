@extends('layouts.app')
@section('title', __('Kunden & Projekte'))
@section('nav-title', __('Kunden & Projekte'))

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
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Aggregierte Stunden und Erlöse pro Kunde und Projekt im gewählten Zeitraum.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.customer-project', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_chart" tone="outline" size="sm"
                            :href="route('reports.customer-project', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'xlsx']))"
                            show-label>XLSX</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.customer-project', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($isAdmin)
        <x-filter-bar :action="route('reports.customer-project')" :reset="route('reports.customer-project')">
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" data-autosubmit>
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur meine') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>
    @endif

    <x-card>
        <div class="mb-3 flex flex-wrap items-baseline justify-end gap-2">
            <div class="flex items-baseline gap-4">
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ Std.</span>
                    <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $totalMinutes > 0 ? 'text-primary' : 'text-base-content/50' }}">
                        {{ $fmt($totalMinutes) }}
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ €</span>
                    <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $totalRate > 0 ? 'text-primary' : 'text-base-content/50' }}">
                        {{ $money($totalRate) }}
                    </span>
                </div>
            </div>
        </div>

        @if (empty($bucket))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">business_center</span>' :title="__('Keine Zeiteinträge im gewählten Zeitraum.')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Kunde / Projekt') }}</th>
                        <th class="text-right">{{ __('Stunden') }}</th>
                        <th class="text-right">{{ __('Erlös') }}</th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>{{ __('Gesamt') }}</td>
                        <td class="text-right">{{ $fmt($totalMinutes) }}</td>
                        <td class="text-right">{{ $money($totalRate) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($bucket as $row)
                    <tr class="bg-base-200/60">
                        <th class="font-semibold text-base-content">
                            {{ $row['customer']?->name ?? __('Ohne Kunde') }}
                        </th>
                        <th class="text-right font-semibold tabular-nums text-base-content">{{ $fmt($row['minutes']) }}</th>
                        <th class="text-right font-semibold tabular-nums text-base-content">{{ $money($row['rate']) }}</th>
                    </tr>
                    @foreach ($row['projects'] as $entry)
                        <tr>
                            <td class="pl-8 text-sm text-base-content/80">
                                @if ($entry['project']->color)
                                    <span class="mr-2 inline-block size-2 rounded-full align-middle" style="background-color: {{ $entry['project']->color }};"></span>
                                @endif
                                {{ $entry['project']->name }}
                                @if ($entry['project']->number)
                                    <span class="ml-1 text-xs text-base-content/50">#{{ $entry['project']->number }}</span>
                                @endif
                                @if ($entry['project']->foreignCustomer)
                                    <span class="ml-1 text-xs text-base-content/50">· {{ $entry['project']->foreignCustomer->name }}</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ $fmt($entry['minutes']) }}</td>
                            <td class="text-right tabular-nums">{{ $money($entry['rate']) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
