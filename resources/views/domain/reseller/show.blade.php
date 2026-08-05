{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $reseller->external_user . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', $reseller->external_user)

@section('content')
<x-page-shell>
    <x-entity-header :title="$reseller->external_user" :back-route="route('domain-reseller.index')" :back-label="__('domain.title.reseller')">
        <x-slot:badges>
            <x-status-badge :tone="$reseller->active ? 'success' : 'ghost'">
                {{ $reseller->active ? __('domain.reseller.active') : __('domain.reseller.inactive') }}
            </x-status-badge>
        </x-slot:badges>
        <x-slot:meta>{{ $reseller->connection->name }}</x-slot:meta>
    </x-entity-header>

    {{-- Übersicht --}}
    <x-card :title="__('domain.section.overview')">
        <x-detail-grid class="sm:grid-cols-[max-content_1fr_max-content_1fr]">
            <x-detail-grid.row :label="__('domain.reseller.parent')" class="font-mono" :value="$reseller->parent_user ?? '—'" />
            <x-detail-grid.row :label="__('domain.reseller.depth')" class="tabular-nums" :value="$reseller->depth" />
            <x-detail-grid.row :label="__('domain.field.customer')" :value="$reseller->customer?->name ?? '—'" />
            <x-detail-grid.row :label="__('domain.reseller.balance')" class="tabular-nums"
                               :value="$reseller->balance_snapshot !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $reseller->balance_snapshot, 2, withThousandsSeparator: true) . ' ' . ($reseller->currency?->value ?? '') : '—'" />
        </x-detail-grid>
        {{-- Kundenzuordnung des Subusers: gruppiert dessen Domains in der
             Kundenakte („geführt unter Subuser …"), ohne direkte
             Domain-Zuordnungen zu überschreiben. --}}
        @if ($canAssign)
            <x-action-form :action="route('domain-reseller.customer', $reseller)" class="mt-3 flex gap-2">
                <select name="customer" class="select select-sm select-bordered w-full" aria-label="{{ __('domain.field.customer') }}">
                    <option value="">{{ __('domain.mapping.none') }}</option>
                    @foreach ($customers as $mappableCustomer)
                        <option value="{{ $mappableCustomer->sqid }}" @selected($reseller->customer_id === $mappableCustomer->id)>{{ $mappableCustomer->name }}</option>
                    @endforeach
                </select>
                <x-icon-btn icon="save" size="sm" type="submit" :title="__('domain.action.save')" />
            </x-action-form>
            <p class="mt-1 text-xs text-base-content/60">{{ __('domain.mapping.reseller_hint') }}</p>
        @endif
    </x-card>

    {{-- Portfolio --}}
    <x-card :title="__('domain.reseller.portfolio')" padding="p-0">
        <x-table size="sm" bare :caption="__('domain.reseller.portfolio')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('domain.field.domain') }}</x-table.th>
                    <x-table.th>{{ __('domain.field.customer') }}</x-table.th>
                    <x-table.th>{{ __('domain.field.expiration') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($reseller->domains as $domain)
                <tr>
                    <td><a href="{{ route('domains.show', $domain) }}" class="link link-hover">{{ $domain->external_domain }}</a>
                        <span class="text-xs text-base-content/50">{{ __('domain.reseller.managed_under', ['user' => $reseller->external_user]) }}</span></td>
                    <td>{{ $domain->customer?->name ?? '—' }}</td>
                    <td class="tabular-nums">{{ $domain->expiration_at?->format('d.m.Y') ?? '—' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="3" :title="__('domain.empty.domains')" compact />
            @endforelse
        </x-table>
    </x-card>

    {{-- Buchungen --}}
    @if ($canViewAccounting)
        <x-card :title="__('domain.section.accounting')" padding="p-0">
            <x-table size="sm" bare :caption="__('domain.section.accounting')">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('domain.accounting.date') }}</x-table.th>
                        <x-table.th>{{ __('domain.accounting.type') }}</x-table.th>
                        <x-table.th>{{ __('domain.accounting.description') }}</x-table.th>
                        <x-table.th align="right">{{ __('domain.accounting.net') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="tabular-nums">{{ $entry->entry_date?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $entry->type ?? '—' }}</td>
                        <td>{{ $entry->description ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $entry->net_amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $entry->net_amount, 2, withThousandsSeparator: true) . ' ' . ($entry->currency?->value ?? '') : '—' }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" :title="__('domain.accounting.empty')" compact />
                @endforelse
            </x-table>
        </x-card>
    @endif

    {{-- Rechnungen (Blocked-State) --}}
    <x-card :title="__('domain.section.invoices')">
        @unless ($invoicesAvailable)
            <div role="note" class="alert bg-info/10 border-info/30 text-sm text-base-content"><span>{{ $invoiceBlockedReason }}</span></div>
        @endunless
    </x-card>
</x-page-shell>
@endsection
