{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Datenführerschaft-Matrix (Restpunkt 69, MVP-1592): Bereich × führendes System, nur schreibfähige Plugins. --}}

@extends('layouts.app')

@section('title', __('Datenführerschaft'))
@section('nav-title', __('Datenführerschaft'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('integration.ownership.matrix.subtitle')">
    <p class="mb-3 shrink-0 text-sm text-muted">{{ __('integration.ownership.matrix.hint_conflicts') }}</p>
    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('integration.ownership.matrix.domain') }}</th>
                <th>{{ __('integration.ownership.matrix.owner') }}</th>
                <th class="text-right">{{ __('integration.ownership.matrix.conflicts') }}</th>
            </tr>
        </x-slot:head>
        @foreach ($rows as $row)
            @php $domain = $row['domain']; @endphp
            <tr data-domain="{{ $domain->value }}">
                <td class="font-medium">{{ $domain->label() }}</td>
                <td>
                    @if ($row['locked'])
                        <div class="flex flex-wrap items-center gap-2">
                            <span>{{ $row['owner_name'] ?? __('integration.ownership.matrix.native') }}</span>
                            <span class="text-sm text-muted">{{ __('integration.ownership.matrix.inventory_mode', ['mode' => $inventoryMode->label()]) }}</span>
                            @foreach ($row['panels'] as $panel)
                                <x-button tone="ghost" size="sm" :href="route($panel['route'])">{{ __('integration.ownership.matrix.inventory_link') }} ({{ $panel['name'] }})</x-button>
                            @endforeach
                        </div>
                    @else
                        <form method="POST" action="{{ route('admin.data-ownership.update') }}" class="flex items-center gap-2">
                            @csrf
                            <input type="hidden" name="domain" value="{{ $domain->value }}">
                            <select name="owner" class="select select-sm select-bordered max-w-72" aria-label="{{ __('integration.ownership.matrix.owner') }}">
                                @foreach ($row['fixed'] as $fixedOwner => $fixedName)
                                    <option value="{{ $fixedOwner }}" @selected($row['owner'] === $fixedOwner)>{{ $fixedName }}</option>
                                @endforeach
                                @foreach ($row['writers'] as $pluginId => $pluginName)
                                    <option value="{{ $pluginId }}" @selected($row['owner'] === $pluginId)>{{ $pluginName }}</option>
                                @endforeach
                                @unless ($row['supported'])
                                    <option value="{{ $row['owner'] }}" selected disabled>{{ __('integration.ownership.matrix.unsupported', ['name' => $row['owner_name']]) }}</option>
                                @endunless
                            </select>
                            <x-icon-btn icon="check" tone="ghost" size="sm" type="submit" :label="__('Speichern')" />
                        </form>
                        @if ($domain === \App\Enums\Integration\DataDomain::Customers)
                            <p class="mt-1 text-sm text-muted">{{ __('integration.ownership.matrix.customers_hint') }}</p>
                        @endif
                    @endif
                </td>
                <td class="text-right">
                    @if ($row['conflicts'] > 0)
                        <a class="link link-warning" href="{{ route('admin.integration.inbox', ['case' => 'ownership']) }}">{{ __('integration.ownership.matrix.open_conflicts', ['count' => $row['conflicts']]) }}</a>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-table>
</x-index-page>
@endsection
