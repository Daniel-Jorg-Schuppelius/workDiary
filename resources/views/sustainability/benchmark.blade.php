{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : benchmark.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Standort-Benchmarking (MVP-929). Erwartet: $year, $rows, $canManage --}}
@extends('layouts.app')

@section('title', __('sustainability.site.benchmark'))
@section('nav-title', __('sustainability.site.benchmark'))

@php
    $t = static fn (float $kg): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($kg / 1000, 2, withThousandsSeparator: true);
@endphp

@section('content')
<x-index-page :subtitle="__('sustainability.site.subtitle')">

    @include('sustainability._tabs')

    <x-filter-bar :action="route('sustainability.sites.benchmark')" :reset="route('sustainability.sites.benchmark')">
        <input type="number" name="year" min="2000" max="2100" value="{{ $year }}" class="input input-sm input-bordered w-28 shrink-0" aria-label="{{ __('sustainability.site.field.year') }}">
    </x-filter-bar>

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('sustainability.site.field.site') }}</th>
                    <th class="text-right">{{ __('sustainability.site.field.area_m2') }}</th>
                    <th class="text-right">{{ __('sustainability.site.field.headcount') }}</th>
                    <th class="text-right">{{ __('sustainability.site.field.co2e_t') }}</th>
                    <th class="text-right">{{ __('sustainability.site.field.per_m2') }}</th>
                    <th class="text-right">{{ __('sustainability.site.field.per_head') }}</th>
                    <th class="text-right">{{ __('sustainability.site.field.missing') }}</th>
                    @if ($canManage)<th></th>@endif
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['site']->name }}@if (! $row['site']->is_active) <span class="badge badge-ghost badge-sm">{{ __('sustainability.site.inactive') }}</span>@endif</td>
                    <td class="text-right tabular-nums">{{ $row['site']->area_m2 !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['site']->area_m2, 0, withThousandsSeparator: true) : '—' }}</td>
                    <td class="text-right tabular-nums">{{ $row['site']->headcount ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $t($row['co2e_kg']) }}</td>
                    <td class="text-right tabular-nums">{{ $row['per_m2'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['per_m2'], 2, withThousandsSeparator: true) : '—' }}</td>
                    <td class="text-right tabular-nums">{{ $row['per_head'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['per_head'], 1, withThousandsSeparator: true) : '—' }}</td>
                    <td class="text-right tabular-nums {{ $row['missing'] > 0 ? 'text-warning' : '' }}">{{ $row['missing'] }}</td>
                    @if ($canManage)
                        <td class="text-right">
                            <details class="inline-block text-left">
                                <summary class="btn btn-ghost btn-xs">{{ __('sustainability.site.edit') }}</summary>
                                <form method="POST" action="{{ route('sustainability.sites.update', $row['site']) }}" class="mt-1 flex flex-wrap items-end gap-1">
                                    @csrf
                                    @method('PUT')
                                    <input name="name" value="{{ $row['site']->name }}" required maxlength="200" class="input input-xs input-bordered w-40" aria-label="{{ __('sustainability.site.field.site') }}">
                                    <input name="area_m2" type="number" step="0.01" min="0" value="{{ $row['site']->area_m2 }}" class="input input-xs input-bordered w-24" aria-label="{{ __('sustainability.site.field.area_m2') }}">
                                    <input name="headcount" type="number" min="0" value="{{ $row['site']->headcount }}" class="input input-xs input-bordered w-20" aria-label="{{ __('sustainability.site.field.headcount') }}">
                                    <input type="hidden" name="is_active" value="0">
                                    <label class="label cursor-pointer gap-1 text-xs"><input type="checkbox" name="is_active" value="1" class="checkbox checkbox-xs" @checked($row['site']->is_active)> {{ __('sustainability.site.field.active') }}</label>
                                    <button type="submit" class="btn btn-xs">{{ __('sustainability.site.save') }}</button>
                                </form>
                            </details>
                        </td>
                    @endif
                </tr>
            @empty
                <x-table.empty icon="location_city" :colspan="$canManage ? 8 : 7" :title="__('sustainability.site.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    @if ($canManage)
        <x-card :title="__('sustainability.site.create')" icon="add_location">
            <form method="POST" action="{{ route('sustainability.sites.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-input-field name="name" :label="__('sustainability.site.field.site')" required />
                <x-input-field name="code" :label="__('sustainability.site.field.code')" />
                <x-input-field name="area_m2" type="number" step="0.01" min="0" :label="__('sustainability.site.field.area_m2')" />
                <x-input-field name="headcount" type="number" min="0" :label="__('sustainability.site.field.headcount')" />
                <x-button type="submit" size="sm" icon="add">{{ __('sustainability.site.save') }}</x-button>
            </form>
        </x-card>
    @endif
    {{-- Vergleich nach Kundengruppe (MVP-949) --}}
    <x-card padding="p-0" class="mt-4" :title="__('sustainability.customer_group.title', ['year' => $year])">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('sustainability.customer_group.group') }}</th>
                    <th class="text-right">{{ __('sustainability.customer_group.customers') }}</th>
                    <th class="text-right">{{ __('sustainability.site.field.co2e_t') }}</th>
                    <th class="text-right">{{ __('sustainability.customer_group.per_customer') }}</th>
                    <th class="text-right">{{ __('sustainability.site.field.missing') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($groupRows as $row)
                <tr>
                    <td>{{ $row['group'] }}</td>
                    <td class="text-right tabular-nums">{{ $row['customers'] }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['co2e_kg'] / 1000, 2, withThousandsSeparator: true) }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['per_customer_kg'], 1, withThousandsSeparator: true) }} kg</td>
                    <td class="text-right tabular-nums">{{ $row['missing'] }}</td>
                </tr>
            @empty
                <x-table.empty icon="groups" :colspan="5" :title="__('sustainability.customer_group.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
