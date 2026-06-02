{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : assets.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Produktanalyse'))
@section('nav-title', __('Produktanalyse'))

@section('content')
<x-index-page :subtitle="__('Defekte, offene Punkte und Aufwand je Asset, Produktgruppe oder Modell.')">
    <x-slot:actions>
        <x-icon-btn icon="download" tone="outline" size="sm"
                    :href="route('reports.assets', array_filter(['customer_id' => \App\Support\Sqid::encode(\App\Models\Customer::class, $customerId), 'category_code' => $categoryCode, 'manufacturer' => $manufacturer, 'group_by' => $groupBy, 'export' => 'csv']))"
                    show-label>CSV</x-icon-btn>
        <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                    :href="route('reports.assets', array_filter(['customer_id' => \App\Support\Sqid::encode(\App\Models\Customer::class, $customerId), 'category_code' => $categoryCode, 'manufacturer' => $manufacturer, 'group_by' => $groupBy, 'export' => 'pdf']))"
                    show-label>PDF</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('reports.assets')" :reset="route('reports.assets')">
        <x-filter-field :label="__('Ebene')" for="rep-group">
            <select id="rep-group" name="group_by" class="select select-sm select-bordered">
                <option value="asset"  @selected($groupBy === 'asset')>{{ __('Pro Asset') }}</option>
                <option value="group"  @selected($groupBy === 'group')>{{ __('Pro Produktgruppe') }}</option>
                <option value="model"  @selected($groupBy === 'model')>{{ __('Pro Modell') }}</option>
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Kunde')" for="rep-customer">
            <select id="rep-customer" name="customer_id" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->sqid }}" @selected(\App\Support\Sqid::encode(\App\Models\Customer::class, $customerId) === $customer->sqid)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Produktgruppe')" for="rep-category">
            <select id="rep-category" name="category_code" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach($categories as $code)
                    <option value="{{ $code }}" @selected($categoryCode === $code)>{{ $code }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Hersteller')" for="rep-manufacturer">
            <select id="rep-manufacturer" name="manufacturer" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach($manufacturers as $m)
                    <option value="{{ $m }}" @selected($manufacturer === $m)>{{ $m }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-card>
        <div class="mb-3 text-xs text-base-content/60">{{ __('Zeitraum') }}: {{ $label }}</div>

        @if(empty($rows))
            <x-empty-state framed
                icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>' />
        @else
            <x-table table-sort="client">
                <x-slot:head>
                    <x-table.th sort type="string">{{ match($groupBy) { 'group' => __('Produktgruppe'), 'model' => __('Modell'), default => __('Asset') } }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Assets') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Aufträge') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Offene Punkte') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Eskaliert') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Defekte') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Defektrate %') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('Letzter Vorfall') }}</x-table.th>
                </x-slot:head>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="text-right">{{ $row['assetCount'] }}</td>
                        <td class="text-right">{{ $row['entryCount'] }}</td>
                        <td class="text-right">
                            @if($row['openIssueCount'] > 0)
                                <a href="{{ route('reports.assets.drilldown.open-issues', $row['drilldown']) }}" class="link link-hover">{{ $row['openIssueCount'] }}</a>
                            @else
                                {{ $row['openIssueCount'] }}
                            @endif
                        </td>
                        <td class="text-right">
                            @if($row['escalationCount'] > 0)
                                <a href="{{ route('reports.assets.drilldown.open-issues', array_merge($row['drilldown'], ['escalated' => 1])) }}" class="link link-hover">{{ $row['escalationCount'] }}</a>
                            @else
                                {{ $row['escalationCount'] }}
                            @endif
                        </td>
                        <td class="text-right">
                            @if($row['defectCount'] > 0)
                                <a href="{{ route('reports.assets.drilldown.protocols', $row['drilldown']) }}" class="link link-hover">{{ $row['defectCount'] }}</a>
                            @else
                                {{ $row['defectCount'] }}
                            @endif
                        </td>
                        <td class="text-right">{{ number_format((float) $row['defectRate'], 2, ',', '.') }}</td>
                        <td @if ($row['lastIncidentAt']) data-sort-value="{{ \Illuminate\Support\Carbon::parse($row['lastIncidentAt'])->format('Y-m-d') }}" @endif>{{ $row['lastIncidentAt'] ? \Illuminate\Support\Carbon::parse($row['lastIncidentAt'])->format('d.m.Y') : '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-index-page>
@endsection
