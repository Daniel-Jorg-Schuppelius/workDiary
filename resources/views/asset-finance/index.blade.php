{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Leasing & Asset-Verträge'))
@section('nav-title', __('Leasing'))

@section('content')
<x-index-page :subtitle="__('Leasing-, Mietkauf-, Finanzierungs- und Nutzungsverträge mit Fristen, Konditionen und Soll-Ist-Sicht — ohne Bilanzierung.')">
    <x-slot:actions>
        @can('create', \App\Models\AssetFinance\AssetFinanceContract::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('asset-finance.create')"
                        show-label>{{ __('Neue Leasingakte') }}</x-icon-btn>
        @endcan
        <x-icon-btn icon="event_upcoming" size="sm" :href="route('asset-finance.deadlines.index')" show-label>{{ __('Fristen') }}</x-icon-btn>
        <x-icon-btn icon="query_stats" size="sm" :href="route('asset-finance.reports.index')" show-label>{{ __('Bericht') }}</x-icon-btn>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-kpi-tile :label="__('Laufende Verträge')" :value="$openCount" />
        <x-kpi-tile :label="__('Enden in ≤ 6 Monaten')" :value="$endingSoonCount" />
    </div>

    <x-filter-bar :action="route('asset-finance.index')" :reset="route('asset-finance.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach (\App\Enums\AssetFinance\AssetFinanceStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <select name="kind" class="select select-sm select-bordered w-52 shrink-0" aria-label="{{ __('Vertragsart') }}">
            <option value="">{{ __('Alle Vertragsarten') }}</option>
            @foreach (\App\Enums\AssetFinance\AssetFinanceKind::cases() as $k)
                <option value="{{ $k->value }}" @selected(request('kind') === $k->value)>{{ $k->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Nummer') }}</th>
                    <th>{{ __('Vertragspartner') }}</th>
                    <th>{{ __('Art') }}</th>
                    <th>{{ __('Assets') }}</th>
                    <th>{{ __('Laufzeit') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($contracts as $contract)
                <tr>
                    <td><a href="{{ route('asset-finance.show', $contract) }}" class="link font-mono">{{ $contract->number }}</a></td>
                    <td>{{ $contract->partner_name }}</td>
                    <td>{{ $contract->kind->label() }}</td>
                    <td>{{ $contract->contractAssets->map(fn($ca) => $ca->asset?->name)->filter()->implode(', ') ?: '—' }}</td>
                    <td>
                        {{ $contract->starts_on->fdate() }} – {{ optional($contract->ends_on)->fdate() ?? __('unbefristet') }}
                        @if ($contract->ends_on !== null && $contract->status->isOpen() && $contract->ends_on <= now()->addMonths(6))
                            <span class="badge badge-warning badge-outline badge-sm">{{ __('endet bald') }}</span>
                        @endif
                    </td>
                    <td><x-status-badge size="md" outline>{{ $contract->status->label() }}</x-status-badge></td>
                    <td class="text-right"><x-icon-btn icon="visibility" :href="route('asset-finance.show', $contract)" :label="__('Anzeigen')" /></td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">request_quote</span>' :colspan="7" :title="__('Keine Leasingakten — Verträge über den Dialog anlegen.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$contracts" standing />
</x-index-page>
@endsection
