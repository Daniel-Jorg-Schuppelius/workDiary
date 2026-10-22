{{--
  Created on   : Mon Jul 06 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  SLA-Verträge (Feature 010): read-only Übersicht.
--}}

@extends('layouts.app')
@section('title', __('SLA-Verträge'))
@section('nav-title', __('SLA-Verträge'))
@include('partials.page-fill')

@section('content')
<x-page-shell overflow="clip">
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:subtitle>{{ __('Reaktions-/Lösungsfristen, Geschäftszeiten, Eskalation und Inklusivzeit-Kontingente je Vertrag.') }}</x-slot:subtitle>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($canManage ?? false)
        {{-- SLA-CRUD (Feature 065, P3): kompaktes Admin-Formular. --}}
        <x-card as="details" padding="p-3">
            <summary class="cursor-pointer text-sm font-semibold">{{ __('Neuen SLA-Vertrag anlegen') }}</summary>
            <form method="POST" action="{{ route('sla-contracts.store') }}" class="mt-2 grid gap-2 md:grid-cols-2">
                @csrf
                @include('sla-contracts._fields', ['contract' => null])
                <div class="md:col-span-2">
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Anlegen') }}</x-icon-btn>
                </div>
            </form>
        </x-card>
    @endif


    <x-table scroll="flex" table-sort="server"
             :route="route('sla-contracts.index')"
             :current-sort="$sort"
             :current-dir="$dir">
        <x-slot:head>
            <tr>
                <x-table.th sort="code">{{ __('Code') }}</x-table.th>
                <x-table.th sort="label">{{ __('Bezeichnung') }}</x-table.th>
                <x-table.th sort="customer">{{ __('Kunde') }}</x-table.th>
                <x-table.th sort="project">{{ __('Projekt') }}</x-table.th>
                <x-table.th sort="quotas" align="right">{{ __('Kontingente') }}</x-table.th>
                <x-table.th sort="status" default>{{ __('Status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($contracts as $contract)
            <tr class="hover">
                <td class="font-mono text-xs">
                    <a href="{{ route('sla-contracts.show', $contract) }}" class="link">{{ $contract->code }}</a>
                </td>
                <td class="font-medium">{{ $contract->label }}</td>
                <td class="text-base-content/70">{{ $contract->customer?->name ?? __('Standard (alle Kunden)') }}</td>
                <td class="text-base-content/70">{{ $contract->project?->name ?? '—' }}</td>
                <td class="text-right tabular-nums">{{ $contract->quotas_count }}</td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @if ($contract->is_default)
                            <x-status-badge tone="info" size="sm">{{ __('Standard') }}</x-status-badge>
                        @endif
                        @if ($contract->is_active)
                            <x-status-badge tone="success" size="sm" outline>{{ __('Aktiv') }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost" size="sm" outline>{{ __('Inaktiv') }}</x-status-badge>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="gavel" :colspan="6" :title="__('Noch keine SLA-Verträge angelegt.')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$contracts" standing />
</x-page-shell>
@endsection
