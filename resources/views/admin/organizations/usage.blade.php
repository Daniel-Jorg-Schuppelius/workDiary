{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : usage.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Nutzung je Mandant (MVP-951). Erwartet: $rows, $organizations, $planRequests --}}
@extends('layouts.app')

@section('title', __('platform_usage.title'))
@section('nav-title', __('platform_usage.title'))

@php
    $size = static fn (int $bytes): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($bytes / 1048576, 1, withThousandsSeparator: true) . ' MB';
@endphp

@section('content')
<x-index-page :subtitle="__('platform_usage.subtitle')"
              back-route="admin.organizations.index" :back-label="__('platform_usage.back')">
    <x-slot:actions>
        <x-icon-btn icon="receipt_long" size="sm" :href="route('admin.organizations.billing')" show-label>{{ __('platform_usage.billing.title') }}</x-icon-btn>
        <x-icon-btn icon="eco" size="sm" :href="route('admin.organizations.branch-benchmark')" show-label>{{ __('platform_usage.benchmark.link') }}</x-icon-btn>
    </x-slot:actions>
    {{-- Offene Tarifanfragen (MVP-957) --}}
    @if ($planRequests->isNotEmpty())
        <x-card :title="__('platform_usage.plan_request.open_title')" padding="p-0" class="mb-4">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('platform_usage.field.organization') }}</th>
                        <th>{{ __('platform_usage.plan_request.field.plan') }}</th>
                        <th>{{ __('platform_usage.plan_request.field.note') }}</th>
                        <th class="text-right">{{ __('Aktionen') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($planRequests as $planRequest)
                    <tr>
                        <td>{{ $planRequest->organization->name ?? '—' }}</td>
                        <td>{{ __('platform_usage.plan.' . $planRequest->requested_plan) }}@if (($planRequest->requested_addons ?? []) !== []) <span class="text-xs text-muted">+ {{ implode(', ', $planRequest->requested_addons) }}</span>@endif</td>
                        <td class="text-sm">{{ $planRequest->note ?? '—' }}</td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                <form method="POST" action="{{ route('admin.organizations.plan-requests.decide', $planRequest->sqid) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="done">
                                    <x-icon-btn icon="check" size="xs" type="submit" :title="__('platform_usage.plan_request.done')" />
                                </form>
                                <form method="POST" action="{{ route('admin.organizations.plan-requests.decide', $planRequest->sqid) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="declined">
                                    <x-icon-btn icon="close" size="xs" type="submit" :title="__('platform_usage.plan_request.decline')" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
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
