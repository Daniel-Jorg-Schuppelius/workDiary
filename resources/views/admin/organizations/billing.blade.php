{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : billing.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Nutzungsabrechnung je Mandant (MVP-956). Erwartet: $snapshots, $months, $month --}}
@extends('layouts.app')

@section('title', __('platform_usage.billing.title'))
@section('nav-title', __('platform_usage.billing.title'))

@php
    $size = static fn (int $bytes): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($bytes / 1073741824, 2, withThousandsSeparator: true) . ' GB';
@endphp

@section('content')
<x-index-page :subtitle="__('platform_usage.billing.subtitle')"
              back-route="admin.organizations.usage" :back-label="__('platform_usage.title')">
    <x-slot:actions>
        <form method="GET" action="{{ route('admin.organizations.billing') }}">
            <select name="month" class="select select-bordered select-sm" aria-label="{{ __('platform_usage.billing.month') }}" data-autosubmit>
                @foreach ($months as $option)
                    <option value="{{ $option }}" @selected($option === $month)>{{ $option }}</option>
                @endforeach
            </select>
        </form>
        @if ($month !== null)
            <x-icon-btn icon="download" size="sm" :href="route('admin.organizations.billing', ['month' => $month, 'export' => 'csv'])" show-label>{{ __('CSV') }}</x-icon-btn>
        @endif
    </x-slot:actions>
    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('platform_usage.field.organization') }}</th>
                    <th>{{ __('platform_usage.billing.plan') }}</th>
                    <th class="text-right">{{ __('platform_usage.field.users') }}</th>
                    <th class="text-right">{{ __('platform_usage.field.active_users') }}</th>
                    <th class="text-right">{{ __('platform_usage.field.storage') }}</th>
                    <th class="text-right">{{ __('platform_usage.field.modules') }}</th>
                    <th class="text-right">{{ __('platform_usage.billing.amount') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($snapshots as $snapshot)
                <tr>
                    <td>{{ $snapshot->organization->name ?? '—' }}</td>
                    <td>{{ __('platform_usage.plan.' . $snapshot->plan) }}@if (($snapshot->addons ?? []) !== []) <span class="text-xs text-muted">+ {{ count($snapshot->addons) }}</span>@endif</td>
                    <td class="text-right tabular-nums">{{ $snapshot->users }}</td>
                    <td class="text-right tabular-nums">{{ $snapshot->active_users ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $size($snapshot->storage_bytes) }}</td>
                    <td class="text-right tabular-nums">{{ $snapshot->modules }}</td>
                    <td class="text-right tabular-nums font-medium">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $snapshot->amount, 2, withThousandsSeparator: true) }} {{ $snapshot->currency }}</td>
                </tr>
            @empty
                <x-table.empty icon="receipt_long" :colspan="7" :title="__('platform_usage.billing.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
    <p class="mt-2 text-xs text-muted">{{ __('platform_usage.billing.note') }}</p>
</x-index-page>
@endsection
