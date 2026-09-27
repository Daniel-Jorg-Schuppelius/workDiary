{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : usage.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Nutzung je Mandant (MVP-951). Erwartet: $rows, $organizations --}}
@extends('layouts.app')

@section('title', __('platform_usage.title'))
@section('nav-title', __('platform_usage.title'))

@php
    $size = static fn (int $bytes): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($bytes / 1048576, 1, withThousandsSeparator: true) . ' MB';
@endphp

@section('content')
<x-index-page :subtitle="__('platform_usage.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="eco" size="sm" :href="route('admin.organizations.branch-benchmark')" show-label>{{ __('platform_usage.benchmark.link') }}</x-icon-btn>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('admin.organizations.index')" show-label>{{ __('platform_usage.back') }}</x-icon-btn>
    </x-slot:actions>
    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('platform_usage.field.organization') }}</th>
                    <th>{{ __('platform_usage.field.status') }}</th>
                    <th class="text-right">{{ __('platform_usage.field.users') }}</th>
                    <th class="text-right">{{ __('platform_usage.field.active_users') }}</th>
                    <th class="text-right">{{ __('platform_usage.field.storage') }}</th>
                    <th class="text-right">{{ __('platform_usage.field.modules') }}</th>
                    <th>{{ __('platform_usage.field.last_activity') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['organization']->name }}</td>
                    <td><span class="wd-badge badge-ghost">{{ $row['status']->label() }}</span></td>
                    <td class="text-right tabular-nums">{{ $row['users'] }}</td>
                    <td class="text-right tabular-nums">{{ $row['active_users'] ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $size($row['bytes']) }}</td>
                    <td class="text-right tabular-nums">{{ $row['modules'] }}</td>
                    <td>{{ $row['last_activity']?->format('d.m.Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <x-table.empty icon="domain" :colspan="7" :title="__('platform_usage.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
    <x-pagination :paginator="$organizations" standing />

</x-index-page>
@endsection
