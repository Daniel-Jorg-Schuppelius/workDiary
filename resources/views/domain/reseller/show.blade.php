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
<x-index-page :subtitle="$reseller->connection->name">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('domain-reseller.index')" show-label>{{ __('domain.title.reseller') }}</x-icon-btn>
    </x-slot:actions>

    @if (session('success'))<div role="alert" class="alert alert-success"><span>{{ session('success') }}</span></div>@endif
    @if (session('error'))<div role="alert" class="alert alert-error"><span>{{ session('error') }}</span></div>@endif

    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-base">{{ __('domain.section.overview') }}</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
                <dt class="text-base-content/60">{{ __('domain.reseller.parent') }}</dt><dd class="font-mono">{{ $reseller->parent_user ?? '—' }}</dd>
                <dt class="text-base-content/60">{{ __('domain.reseller.depth') }}</dt><dd class="tabular-nums">{{ $reseller->depth }}</dd>
                <dt class="text-base-content/60">{{ __('domain.field.customer') }}</dt><dd>{{ $reseller->customer?->name ?? '—' }}</dd>
                <dt class="text-base-content/60">{{ __('domain.reseller.balance') }}</dt>
                <dd class="tabular-nums">{{ $reseller->balance_snapshot !== null ? number_format((float) $reseller->balance_snapshot, 2, ',', '.') . ' ' . ($reseller->currency?->value ?? '') : '—' }}</dd>
            </dl>
        </div>
    </div>

    {{-- Portfolio --}}
    <div class="card bg-base-100 shadow-sm mt-4">
        <div class="card-body">
            <h2 class="card-title text-base">{{ __('domain.reseller.portfolio') }}</h2>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead><tr><th>{{ __('domain.field.domain') }}</th><th>{{ __('domain.field.customer') }}</th><th>{{ __('domain.field.expiration') }}</th></tr></thead>
                    <tbody>
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
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Buchungen --}}
    @if ($canViewAccounting)
        <div class="card bg-base-100 shadow-sm mt-4">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('domain.section.accounting') }}</h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('domain.accounting.date') }}</th><th>{{ __('domain.accounting.type') }}</th><th>{{ __('domain.accounting.description') }}</th><th class="text-right">{{ __('domain.accounting.net') }}</th></tr></thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                <tr>
                                    <td class="tabular-nums">{{ $entry->entry_date?->format('d.m.Y') ?? '—' }}</td>
                                    <td>{{ $entry->type ?? '—' }}</td>
                                    <td>{{ $entry->description ?? '—' }}</td>
                                    <td class="text-right tabular-nums">{{ $entry->net_amount !== null ? number_format((float) $entry->net_amount, 2, ',', '.') . ' ' . ($entry->currency?->value ?? '') : '—' }}</td>
                                </tr>
                            @empty
                                <x-table.empty :colspan="4" :title="__('domain.accounting.empty')" compact />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Rechnungen (Blocked-State) --}}
    <div class="card bg-base-100 shadow-sm mt-4">
        <div class="card-body">
            <h2 class="card-title text-base">{{ __('domain.section.invoices') }}</h2>
            @unless ($invoicesAvailable)
                <div role="alert" class="alert alert-info"><span>{{ $invoiceBlockedReason }}</span></div>
            @endunless
        </div>
    </div>
</x-index-page>
@endsection
