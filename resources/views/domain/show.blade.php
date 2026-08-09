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
<x-page-shell>
    <x-entity-header :title="$domain->external_domain" :back-route="route('domains.index')" :back-label="__('domain.title.index')">
        <x-slot:badges>
            <x-status-badge :tone="$domain->sync_status->badge()">{{ $domain->sync_status->label() }}</x-status-badge>
        </x-slot:badges>
        <x-slot:meta>{{ $domain->connection->name }} · {{ $domain->external_user }}</x-slot:meta>
        <x-slot:actions>
            <x-action-form :action="route('domains.refresh', $domain)">
                <x-icon-btn icon="sync" size="sm" type="submit" show-label>{{ __('domain.action.refresh') }}</x-icon-btn>
            </x-action-form>
        </x-slot:actions>
    </x-entity-header>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Übersicht --}}
        <x-card :title="__('domain.section.overview')" class="lg:col-span-2">
            <x-detail-grid>
                <x-detail-grid.row :label="__('domain.field.registrar')" :value="$domain->registrar ?? '—'" />
                <x-detail-grid.row :label="__('domain.field.status')" :value="$domain->status ?? '—'" />
                <x-detail-grid.row :label="__('domain.field.expiration')" class="tabular-nums" :value="$domain->expiration_at?->format('d.m.Y') ?? '—'" />
                <x-detail-grid.row :label="__('domain.field.renewal_mode')" :value="$domain->renewal_mode?->label() ?? '—'" />
                <x-detail-grid.row :label="__('domain.field.transferlock')" :value="$domain->transferlock ? __('domain.yes') : __('domain.no')" />
                <x-detail-grid.row :label="__('domain.field.renewal_price')"
                                   :value="$domain->renewal_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $domain->renewal_price, 2, withThousandsSeparator: true) . ' ' . ($domain->renewal_currency?->value ?? '') : '—'" />
                <x-detail-grid.row :label="__('domain.field.sync')">
                    <x-status-badge :tone="$domain->sync_status->badge()">{{ $domain->sync_status->label() }}</x-status-badge>
                    <span class="text-xs text-base-content/50">{{ $domain->synced_at?->diffForHumans() }}</span>
                </x-detail-grid.row>
            </x-detail-grid>
        </x-card>

        {{-- Kunden-/Endkunden-Zuordnung + Eigenbestand --}}
        <x-card :title="__('domain.field.customer')">
            @if ($domain->is_own_holding)
                <p class="text-sm">{{ __('domain.mapping.own_holding') }}</p>
            @else
                <p class="text-sm">
                    {{ $domain->customer?->name ?? __('domain.mapping.none') }}
                    @if ($domain->foreignCustomer !== null)
                        <span class="text-base-content/60">· {{ __('domain.mapping.foreign_customer') }}: {{ $domain->foreignCustomer->name }}</span>
                    @endif
                </p>
            @endif
            @if ($can['assign'])
                {{-- Match-Vorschläge (nachvollziehbare Merkmale, nie automatisch bestätigt) --}}
                @if ($suggestions !== [])
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($suggestions as $suggestion)
                            <x-action-form :action="route('domains.customer', $domain)">
                                <input type="hidden" name="customer" value="{{ $suggestion['customer']->sqid }}">
                                <button type="submit" class="btn btn-xs btn-outline">
                                    {{ $suggestion['customer']->name }}
                                    <span class="text-base-content/50">({{ __('domain.mapping.reason_' . $suggestion['reason']) }})</span>
                                </button>
                            </x-action-form>
                        @endforeach
                    </div>
                @endif
                @unless ($domain->is_own_holding)
                    <x-action-form :action="route('domains.customer', $domain)" class="mt-2">
                        <div class="space-y-2"
                             x-data="{
                                 map: @js($foreignByCustomer),
                                 customer: @js($domain->customer?->sqid ?? ''),
                                 foreign: @js($domain->foreignCustomer?->sqid ?? ''),
                             }"
                             x-init="$watch('customer', () => { foreign = '' })">
                            <select name="customer" x-model="customer" class="select select-sm select-bordered w-full" aria-label="{{ __('domain.field.customer') }}">
                                <option value="">{{ __('domain.mapping.none') }}</option>
                                @foreach ($customers as $mappableCustomer)
                                    <option value="{{ $mappableCustomer->sqid }}">{{ $mappableCustomer->name }}</option>
                                @endforeach
                            </select>
                            <select name="foreign_customer" x-model="foreign" x-show="(map[customer] ?? []).length > 0" x-cloak
                                    class="select select-sm select-bordered w-full" aria-label="{{ __('domain.mapping.foreign_customer') }}">
                                <option value="">{{ __('domain.mapping.no_foreign_customer') }}</option>
                                <template x-for="fc in (map[customer] ?? [])" :key="fc.sqid">
                                    <option :value="fc.sqid" x-text="fc.name"></option>
                                </template>
                            </select>
                            <div class="flex justify-end">
                                <x-icon-btn icon="save" size="sm" type="submit" show-label>{{ __('domain.action.save') }}</x-icon-btn>
                            </div>
                        </div>
                    </x-action-form>
                @endunless
                {{-- Eigenbestand-Umschalter (schließt Kundenzuordnung aus) --}}
                <x-action-form :action="route('domains.customer', $domain)" class="mt-2">
                    <input type="hidden" name="own" value="{{ $domain->is_own_holding ? '0' : '1' }}">
                    <button type="submit" class="btn btn-xs btn-ghost">
                        {{ $domain->is_own_holding ? __('domain.mapping.own_clear') : __('domain.mapping.own_mark') }}
                    </button>
                </x-action-form>
            @endif
        </x-card>
    </div>

    {{-- DNS --}}
    <x-card :title="__('domain.section.dns')">
        <x-slot:actions>
            @if ($can['dns'])
                <x-action-form :action="route('domains.dns.read', $domain)">
                    <x-icon-btn icon="download" size="xs" type="submit" show-label>{{ __('domain.action.dns_read') }}</x-icon-btn>
                </x-action-form>
            @endif
        </x-slot:actions>
        @forelse ($domain->dnsZones as $zone)
            <div class="text-xs font-mono text-base-content/70 mt-2 mb-1">{{ $zone->zone }}</div>
            <x-table size="xs" :zebra="false" bare :caption="__('domain.section.dns') . ' — ' . $zone->zone">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('domain.dns.type') }}</x-table.th>
                        <x-table.th>{{ __('domain.dns.name') }}</x-table.th>
                        <x-table.th>TTL</x-table.th>
                        <x-table.th>{{ __('domain.dns.content') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($zone->records as $record)
                    <tr>
                        <td>{{ $record->type->value }}</td>
                        <td class="font-mono">{{ $record->name }}</td>
                        <td class="tabular-nums">{{ $record->ttl }}</td>
                        <td class="font-mono">{{ $record->content }}</td>
                    </tr>
                @endforeach
            </x-table>
        @empty
            <p class="text-sm text-base-content/60">{{ __('domain.dns.empty') }}</p>
        @endforelse
    </x-card>

    {{-- Rechnungen (Blocked-State / capability-gegatet) --}}
    <x-card :title="__('domain.section.invoices')">
        @unless ($invoicesAvailable)
            <div role="note" class="alert bg-info/10 border-info/30 text-sm text-base-content"><span>{{ $invoiceBlockedReason }}</span></div>
        @else
            <p class="text-sm text-base-content/60">{{ __('domain.invoices.available') }}</p>
        @endunless
    </x-card>

    {{-- Timeline: Provider-Commands --}}
    <x-card :title="__('domain.section.timeline')" padding="p-0">
        <x-table size="sm" bare :caption="__('domain.section.timeline')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('domain.command.name') }}</x-table.th>
                    <x-table.th>{{ __('domain.command.status') }}</x-table.th>
                    <x-table.th>{{ __('domain.command.when') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($commands as $command)
                <tr>
                    <td class="font-mono text-xs">{{ $command->command }}</td>
                    <td><x-status-badge :tone="$command->status->badge()">{{ $command->status->label() }}</x-status-badge></td>
                    <td class="text-xs text-base-content/60">{{ $command->created_at?->diffForHumans() }}</td>
                    <td class="text-right">
                        @if ($can['dangerous'] && $command->status === \App\Enums\Domain\DomainProviderCommandStatus::Draft)
                            <x-action-form :action="route('domains.commands.approve', $command)">
                                <x-icon-btn icon="how_to_reg" tone="warning" size="xs" type="submit" :title="__('domain.action.approve')" />
                            </x-action-form>
                            <x-action-form :action="route('domains.commands.reject', $command)">
                                <x-icon-btn icon="cancel" tone="error" size="xs" type="submit" :title="__('domain.action.reject')" />
                            </x-action-form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="4" :title="__('domain.command.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    {{-- Aktionen --}}
    @if ($can['renewal'] || $can['transfer'] || $can['dangerous'])
        <x-card :title="__('domain.section.actions')" class="space-y-3">
            @if ($can['renewal'])
                <x-action-form :action="route('domains.renewal-mode', $domain)" class="flex flex-wrap items-end gap-2">
                    <select name="renewal_mode" class="select select-sm select-bordered" aria-label="{{ __('domain.field.renewal_mode') }}">
                        @foreach (\App\Enums\Domain\DomainRenewalMode::cases() as $m)
                            <option value="{{ $m->value }}" @selected($domain->renewal_mode === $m)>{{ $m->label() }}</option>
                        @endforeach
                    </select>
                    <x-icon-btn icon="autorenew" size="sm" type="submit" show-label>{{ __('domain.action.set_renewal_mode') }}</x-icon-btn>
                </x-action-form>
            @endif

            @if ($can['transfer'])
                <x-action-form :action="route('domains.transfer-lock', $domain)" class="flex items-end gap-2">
                    <input type="hidden" name="locked" value="{{ $domain->transferlock ? 0 : 1 }}">
                    <x-icon-btn icon="{{ $domain->transferlock ? 'lock_open' : 'lock' }}" size="sm" type="submit" show-label>
                        {{ $domain->transferlock ? __('domain.action.unlock_transfer') : __('domain.action.lock_transfer') }}
                    </x-icon-btn>
                </x-action-form>
            @endif

            @if ($can['dangerous'])
                <x-action-form :action="route('domains.dangerous', $domain)" class="flex flex-wrap items-end gap-2 border-t border-base-200 pt-3"
                               :confirm="__('domain.action.dangerous_confirm')" confirm-tone="error">
                    <select name="action" class="select select-sm select-bordered" aria-label="{{ __('domain.action.request_dangerous') }}">
                        <option value="delete">{{ __('domain.dangerous.delete') }}</option>
                        <option value="push">{{ __('domain.dangerous.push') }}</option>
                        <option value="trade">{{ __('domain.dangerous.trade') }}</option>
                        <option value="transfer_out">{{ __('domain.dangerous.transfer_out') }}</option>
                        <option value="assign">{{ __('domain.dangerous.assign') }}</option>
                    </select>
                    <input type="text" name="target_user" class="input input-sm input-bordered w-40" placeholder="{{ __('domain.dangerous.target_user') }}" aria-label="{{ __('domain.dangerous.target_user') }}">
                    <input type="text" name="confirmation" class="input input-sm input-bordered w-56" placeholder="{{ __('domain.dangerous.retype', ['domain' => $domain->external_domain]) }}" aria-label="{{ __('domain.dangerous.retype', ['domain' => $domain->external_domain]) }}" required>
                    <x-icon-btn icon="warning" tone="error" size="sm" type="submit" show-label>{{ __('domain.action.request_dangerous') }}</x-icon-btn>
                    <p class="w-full text-xs text-base-content/60">{{ __('domain.dangerous.hint') }}</p>
                </x-action-form>
            @endif
        </x-card>
    @endif
</x-page-shell>
@endsection
