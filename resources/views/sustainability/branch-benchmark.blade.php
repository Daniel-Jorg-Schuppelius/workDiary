{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : branch-benchmark.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anonymer Branchenvergleich (MVP-949), nur Plattformbetrieb. Erwartet: $benchmark, $year --}}
@extends('layouts.app')

@section('title', __('platform_usage.benchmark.link'))
@section('nav-title', __('platform_usage.benchmark.link'))

@php
    $t = static fn (float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 1, withThousandsSeparator: true);
@endphp

@section('content')
<x-index-page :subtitle="__('platform_usage.benchmark.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('admin.organizations.usage')" show-label>{{ __('platform_usage.title') }}</x-icon-btn>
    </x-slot:actions>
    <x-card padding="p-0" :title="__('platform_usage.benchmark.title', ['year' => $year])">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('platform_usage.benchmark.branch') }}</th>
                    <th class="text-right">{{ __('platform_usage.benchmark.organizations') }}</th>
                    <th class="text-right">{{ __('platform_usage.benchmark.mean') }}</th>
                    <th class="text-right">{{ __('platform_usage.benchmark.median') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($benchmark as $row)
                <tr>
                    <td>{{ $row['branch'] }}</td>
                    <td class="text-right tabular-nums">{{ $row['organizations'] }}</td>
                    <td class="text-right tabular-nums">{{ $t($row['mean_t']) }} t</td>
                    <td class="text-right tabular-nums">{{ $t($row['median_t']) }} t</td>
                </tr>
            @empty
                <x-table.empty icon="eco" :colspan="4" :title="__('platform_usage.benchmark.empty', ['min' => \App\Services\Sustainability\BranchEmissionBenchmarkService::MIN_ORGANIZATIONS])" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
