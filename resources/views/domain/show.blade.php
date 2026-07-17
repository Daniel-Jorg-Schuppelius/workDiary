{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $domain->external_domain . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', $domain->external_domain)

@section('content')
<x-index-page :subtitle="$domain->connection->name . ' · ' . $domain->external_user">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('domains.index')" show-label>{{ __('domain.title.index') }}</x-icon-btn>
        <form method="POST" action="{{ route('domains.refresh', $domain) }}" class="inline">
            @csrf
            <x-icon-btn icon="sync" size="sm" type="submit" show-label>{{ __('domain.action.refresh') }}</x-icon-btn>
        </form>
    </x-slot:actions>

    @if (session('success'))<div role="alert" class="alert alert-success"><span>{{ session('success') }}</span></div>@endif
    @if (session('error'))<div role="alert" class="alert alert-error"><span>{{ session('error') }}</span></div>@endif

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Übersicht --}}
        <div class="card bg-base-100 shadow-sm lg:col-span-2">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('domain.section.overview') }}</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <dt class="text-base-content/60">{{ __('domain.field.registrar') }}</dt><dd>{{ $domain->registrar ?? '—' }}</dd>
                    <dt class="text-base-content/60">{{ __('domain.field.status') }}</dt><dd>{{ $domain->status ?? '—' }}</dd>
                    <dt class="text-base-content/60">{{ __('domain.field.expiration') }}</dt><dd class="tabular-nums">{{ $domain->expiration_at?->format('d.m.Y') ?? '—' }}</dd>
                    <dt class="text-base-content/60">{{ __('domain.field.renewal_mode') }}</dt><dd>{{ $domain->renewal_mode?->label() ?? '—' }}</dd>
                    <dt class="text-base-content/60">{{ __('domain.field.transferlock') }}</dt><dd>{{ $domain->transferlock ? __('domain.yes') : __('domain.no') }}</dd>
                    <dt class="text-base-content/60">{{ __('domain.field.renewal_price') }}</dt>
                    <dd>{{ $domain->renewal_price !== null ? number_format((float) $domain->renewal_price, 2, ',', '.') . ' ' . ($domain->renewal_currency?->value ?? '') : '—' }}</dd>
                    <dt class="text-base-content/60">{{ __('domain.field.sync') }}</dt>
                    <dd><span class="badge badge-{{ $domain->sync_status->badge() }} badge-sm">{{ $domain->sync_status->label() }}</span>
                        <span class="text-xs text-base-content/50">{{ $domain->synced_at?->diffForHumans() }}</span></dd>
                </dl>
            </div>
        </div>

        {{-- Kundenzuordnung --}}
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('domain.field.customer') }}</h2>
                <p class="text-sm">{{ $domain->customer?->name ?? __('domain.mapping.none') }}</p>
                @if ($can['assign'])
                    <form method="POST" action="{{ route('domains.customer', $domain) }}" class="mt-2 flex gap-2">
                        @csrf
                        <input type="text" name="customer" class="input input-sm input-bordered w-full"
                               placeholder="{{ __('domain.mapping.customer_sqid') }}">
                        <x-icon-btn icon="save" size="sm" type="submit" :title="__('domain.action.save')" />
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- DNS --}}
    <div class="card bg-base-100 shadow-sm mt-4">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <h2 class="card-title text-base">{{ __('domain.section.dns') }}</h2>
                @if ($can['dns'])
                    <form method="POST" action="{{ route('domains.dns.read', $domain) }}">
                        @csrf
                        <x-icon-btn icon="download" size="xs" type="submit" show-label>{{ __('domain.action.dns_read') }}</x-icon-btn>
                    </form>
                @endif
            </div>
            @forelse ($domain->dnsZones as $zone)
                <div class="text-xs font-mono text-base-content/70 mt-2">{{ $zone->zone }}</div>
                <div class="overflow-x-auto">
                    <table class="table table-xs">
                        <thead><tr><th>{{ __('domain.dns.type') }}</th><th>{{ __('domain.dns.name') }}</th><th>TTL</th><th>{{ __('domain.dns.content') }}</th></tr></thead>
                        <tbody>
                            @foreach ($zone->records as $record)
                                <tr><td>{{ $record->type->value }}</td><td class="font-mono">{{ $record->name }}</td><td class="tabular-nums">{{ $record->ttl }}</td><td class="font-mono">{{ $record->content }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <p class="text-sm text-base-content/60">{{ __('domain.dns.empty') }}</p>
            @endforelse
        </div>
    </div>

    {{-- Rechnungen (Blocked-State / capability-gegatet) --}}
    <div class="card bg-base-100 shadow-sm mt-4">
        <div class="card-body">
            <h2 class="card-title text-base">{{ __('domain.section.invoices') }}</h2>
            @unless ($invoicesAvailable)
                <div role="alert" class="alert alert-info"><span>{{ $invoiceBlockedReason }}</span></div>
            @else
                <p class="text-sm text-base-content/60">{{ __('domain.invoices.available') }}</p>
            @endunless
        </div>
    </div>

    {{-- Timeline: Provider-Commands --}}
    <div class="card bg-base-100 shadow-sm mt-4">
        <div class="card-body">
            <h2 class="card-title text-base">{{ __('domain.section.timeline') }}</h2>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead><tr><th>{{ __('domain.command.name') }}</th><th>{{ __('domain.command.status') }}</th><th>{{ __('domain.command.when') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($commands as $command)
                            <tr>
                                <td class="font-mono text-xs">{{ $command->command }}</td>
                                <td><span class="badge badge-{{ $command->status->badge() }} badge-sm">{{ $command->status->label() }}</span></td>
                                <td class="text-xs text-base-content/60">{{ $command->created_at?->diffForHumans() }}</td>
                                <td class="text-right">
                                    @if ($can['dangerous'] && $command->status === \App\Enums\Domain\DomainProviderCommandStatus::Draft)
                                        <form method="POST" action="{{ route('domains.commands.approve', $command) }}" class="inline">
                                            @csrf
                                            <x-icon-btn icon="how_to_reg" tone="warning" size="xs" type="submit" :title="__('domain.action.approve')" />
                                        </form>
                                        <form method="POST" action="{{ route('domains.commands.reject', $command) }}" class="inline">
                                            @csrf
                                            <x-icon-btn icon="cancel" tone="error" size="xs" type="submit" :title="__('domain.action.reject')" />
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-table.empty :colspan="4" :title="__('domain.command.empty')" compact />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Aktionen --}}
    @if ($can['renewal'] || $can['transfer'] || $can['dangerous'])
        <div class="card bg-base-100 shadow-sm mt-4">
            <div class="card-body space-y-3">
                <h2 class="card-title text-base">{{ __('domain.section.actions') }}</h2>

                @if ($can['renewal'])
                    <form method="POST" action="{{ route('domains.renewal-mode', $domain) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <select name="renewal_mode" class="select select-sm select-bordered">
                            @foreach (\App\Enums\Domain\DomainRenewalMode::cases() as $m)
                                <option value="{{ $m->value }}" @selected($domain->renewal_mode === $m)>{{ $m->label() }}</option>
                            @endforeach
                        </select>
                        <x-icon-btn icon="autorenew" size="sm" type="submit" show-label>{{ __('domain.action.set_renewal_mode') }}</x-icon-btn>
                    </form>
                @endif

                @if ($can['transfer'])
                    <form method="POST" action="{{ route('domains.transfer-lock', $domain) }}" class="flex items-end gap-2">
                        @csrf
                        <input type="hidden" name="locked" value="{{ $domain->transferlock ? 0 : 1 }}">
                        <x-icon-btn icon="{{ $domain->transferlock ? 'lock_open' : 'lock' }}" size="sm" type="submit" show-label>
                            {{ $domain->transferlock ? __('domain.action.unlock_transfer') : __('domain.action.lock_transfer') }}
                        </x-icon-btn>
                    </form>
                @endif

                @if ($can['dangerous'])
                    <form method="POST" action="{{ route('domains.dangerous', $domain) }}" class="flex flex-wrap items-end gap-2 border-t border-base-200 pt-3"
                          data-confirm-dialog data-confirm-message="{{ __('domain.action.dangerous_confirm') }}" data-confirm-tone="error">
                        @csrf
                        <select name="action" class="select select-sm select-bordered">
                            <option value="delete">{{ __('domain.dangerous.delete') }}</option>
                            <option value="push">{{ __('domain.dangerous.push') }}</option>
                            <option value="trade">{{ __('domain.dangerous.trade') }}</option>
                            <option value="transfer_out">{{ __('domain.dangerous.transfer_out') }}</option>
                            <option value="assign">{{ __('domain.dangerous.assign') }}</option>
                        </select>
                        <input type="text" name="target_user" class="input input-sm input-bordered w-40" placeholder="{{ __('domain.dangerous.target_user') }}">
                        <input type="text" name="confirmation" class="input input-sm input-bordered w-56" placeholder="{{ __('domain.dangerous.retype', ['domain' => $domain->external_domain]) }}" required>
                        <x-icon-btn icon="warning" tone="error" size="sm" type="submit" show-label>{{ __('domain.action.request_dangerous') }}</x-icon-btn>
                        <p class="w-full text-xs text-base-content/60">{{ __('domain.dangerous.hint') }}</p>
                    </form>
                @endif
            </div>
        </div>
    @endif
</x-index-page>
@endsection
