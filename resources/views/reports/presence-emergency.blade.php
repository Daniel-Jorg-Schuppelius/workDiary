@extends('layouts.app')
@section('title', __('reporting.presence_emergency.title'))
@section('nav-title', __('reporting.presence_emergency.title'))

@section('content')
@php
    $tz = \App\Support\Tz::current();
    $locale = app()->getLocale();
    $fmt = fn ($c) => $c?->setTimezone($tz)->locale($locale)->isoFormat('L LT');
    $atLabel = $fmt($snapshot['at']);
    $queryBase = array_filter([
        'at' => request()->query('at'),
        'site' => request()->query('site'),
    ], fn ($v) => $v !== null && $v !== '');
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('reporting.presence_emergency.subtitle')">
            <x-slot:actions>
                <x-icon-btn icon="print" tone="outline" size="sm" data-print show-label
                            :label="__('reporting.presence_emergency.print')">{{ __('reporting.presence_emergency.print') }}</x-icon-btn>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.presence-emergency', array_merge($queryBase, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.presence-emergency', array_merge($queryBase, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.presence-emergency')" :reset="route('reports.presence-emergency')">
        <x-filter-field :label="__('reporting.presence_emergency.at_label')" for="pe-at">
            <input id="pe-at" type="datetime-local" name="at" value="{{ $atLocal }}"
                   class="input input-sm input-bordered" />
        </x-filter-field>
        @if ($siteOptions !== [])
            <x-filter-field :label="__('reporting.presence_emergency.site_label')" for="pe-site" class="min-w-44">
                <select id="pe-site" name="site" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('reporting.presence_emergency.all_sites') }}</option>
                    @foreach ($siteOptions as $option)
                        <option value="{{ $option['sqid'] }}" @selected($siteId === $option['id'])>{{ $option['name'] }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
        <x-icon-btn icon="update" type="submit" tone="primary" size="sm" show-label>{{ __('reporting.presence_emergency.apply') }}</x-icon-btn>
    </x-filter-bar>

    <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/70">
        <span class="wd-badge {{ $snapshot['is_live'] ? 'badge-success' : 'badge-warning' }} badge-outline">
            {{ $snapshot['is_live'] ? __('reporting.presence_emergency.live') : __('reporting.presence_emergency.reconstructed') }}
        </span>
        <span>{{ __('reporting.presence_emergency.stand') }}: <strong>{{ $atLabel }}</strong></span>
        <span>· {{ __('reporting.presence_emergency.generated') }}: {{ $fmt($generatedAt) }}</span>
    </div>

    <div class="grid gap-3 grid-cols-2 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('reporting.presence_emergency.group_present')"
                    :value="count($snapshot['present']) + count($snapshot['present_unmapped'])" tone="success" />
        <x-kpi-tile :label="__('reporting.presence_emergency.group_off_site')" :value="count($snapshot['off_site'])" tone="info" />
        <x-kpi-tile :label="__('reporting.presence_emergency.group_absent')" :value="count($snapshot['absent'])" />
        <x-kpi-tile :label="__('reporting.presence_emergency.group_unaccounted')" :value="count($snapshot['unaccounted'])"
                    :tone="count($snapshot['unaccounted']) > 0 ? 'warning' : 'neutral'" />
    </div>

    <x-card :title="__('reporting.presence_emergency.group_present')">
        @if ($snapshot['present'] === [])
            <p class="text-sm text-base-content/60">{{ __('reporting.presence_emergency.empty_group') }}</p>
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('reporting.presence_emergency.col_name') }}</x-table.th>
                        <x-table.th>{{ __('reporting.presence_emergency.col_since') }}</x-table.th>
                        <x-table.th>{{ __('reporting.presence_emergency.col_site') }}</x-table.th>
                        <x-table.th></x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($snapshot['present'] as $row)
                    <tr>
                        <td class="font-semibold">{{ $row['user']->name }}</td>
                        <td class="tabular-nums">{{ $fmt($row['since']) }}</td>
                        <td>{{ $row['site_name'] ?? '—' }}</td>
                        <td>
                            @if ($row['on_break'])
                                <span class="wd-badge badge-outline">{{ __('reporting.presence_emergency.on_break') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    @if ($snapshot['present_unmapped'] !== [])
        <x-card :title="__('reporting.presence_emergency.group_present_unmapped')">
            <p class="mb-2 text-sm text-warning">{{ __('reporting.presence_emergency.unmapped_hint') }}</p>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('reporting.presence_emergency.col_name') }}</x-table.th>
                        <x-table.th>{{ __('reporting.presence_emergency.col_since') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($snapshot['present_unmapped'] as $row)
                    <tr>
                        <td class="font-semibold">{{ $row['user']->name }}</td>
                        <td class="tabular-nums">{{ $fmt($row['since']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    <x-card :title="__('reporting.presence_emergency.group_off_site')">
        @if ($snapshot['off_site'] === [])
            <p class="text-sm text-base-content/60">{{ __('reporting.presence_emergency.empty_group') }}</p>
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('reporting.presence_emergency.col_name') }}</x-table.th>
                        <x-table.th>{{ __('reporting.presence_emergency.col_since') }}</x-table.th>
                        <x-table.th>{{ __('reporting.presence_emergency.col_context') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($snapshot['off_site'] as $row)
                    <tr>
                        <td class="font-semibold">{{ $row['user']->name }}</td>
                        <td class="tabular-nums">{{ $fmt($row['since']) }}</td>
                        <td>{{ $row['context'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-card :title="__('reporting.presence_emergency.group_absent')">
        @if ($snapshot['absent'] === [])
            <p class="text-sm text-base-content/60">{{ __('reporting.presence_emergency.empty_group') }}</p>
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('reporting.presence_emergency.col_name') }}</x-table.th>
                        <x-table.th>{{ __('reporting.presence_emergency.col_reason') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($snapshot['absent'] as $row)
                    <tr>
                        <td class="font-semibold">{{ $row['user']->name }}</td>
                        <td>{{ __('reporting.presence_emergency.reason_' . $row['reason']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-card :title="__('reporting.presence_emergency.group_unaccounted')">
        <p class="mb-2 text-sm text-base-content/60">{{ __('reporting.presence_emergency.unaccounted_hint') }}</p>
        @if ($snapshot['unaccounted'] === [])
            <p class="text-sm text-base-content/60">{{ __('reporting.presence_emergency.empty_group') }}</p>
        @else
            <ul class="grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($snapshot['unaccounted'] as $row)
                    <li class="text-sm font-semibold">{{ $row['user']->name }}</li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <p class="text-xs text-base-content/50">{{ __('reporting.presence_emergency.deviation_note') }}</p>
</x-page-shell>

@include('partials.print-script')
@endsection
