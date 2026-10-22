{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Servicelandkarte (Feature 065, MVP-1256). Variablen: $rows (service, affected, openTickets), $canManage --}}

@extends('layouts.app')
@section('title', __('helpdesk.map.title'))
@section('nav-title', __('helpdesk.map.title'))
@include('partials.page-fill')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('helpdesk.map.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="category" size="sm" :href="route('servicedesk.catalog.index')" show-label>{{ __('Servicekatalog') }}</x-icon-btn>
        </x-slot:actions>

        @if ($rows->isEmpty())
            <x-empty-state framed icon="account_tree" :title="__('helpdesk.map.empty')" />
        @else
            <x-table scroll="flex">
                <x-slot:head>
                    <th>{{ __('helpdesk.map.service') }}</th>
                    <th>{{ __('helpdesk.map.dependsOn') }}</th>
                    <th>{{ __('helpdesk.map.dependents') }}</th>
                    <th class="text-right">{{ __('helpdesk.map.affected') }}</th>
                    <th class="text-right">{{ __('helpdesk.map.openTickets') }}</th>
                </x-slot:head>
                @foreach ($rows as $row)
                    @php $service = $row['service']; @endphp
                    <tr>
                        <td>
                            <span class="font-medium">{{ $service->name }}</span>
                            @unless ($service->active)
                                <x-status-badge tone="ghost" size="xs">{{ __('Inaktiv') }}</x-status-badge>
                            @endunless
                            @if ($service->description)
                                <div class="text-xs text-muted">{{ $service->description }}</div>
                            @endif
                        </td>
                        <td class="text-sm">{{ $service->dependsOn->pluck('name')->sort()->join(', ') ?: '—' }}</td>
                        <td class="text-sm">{{ $service->dependents->pluck('name')->sort()->join(', ') ?: '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['affected'] }}</td>
                        <td class="text-right tabular-nums">
                            @if ($row['openTickets'] > 0)
                                <a class="link" href="{{ route('service-tickets.index', ['service' => $service->sqid]) }}">{{ $row['openTickets'] }}</a>
                            @else
                                0
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-index-page>
@endsection
