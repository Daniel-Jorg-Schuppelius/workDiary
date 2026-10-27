{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abo-Liste des Reselling-Registers (Feature 152, MVP-758): alle Abos mit
  Halter, Laufzeit, Preisen und der Zahl offener Perioden. Ohne Statusfilter
  nur planbare Abos (aktiv, gekündigt); beendete und abgelöste über den
  Filter. Der Überblick (Aufgabenliste) liegt seit Feature 185 auf dem Index.
--}}
@extends('layouts.app')
@section('title', __('resale.title.subscriptions'))
@section('nav-title', __('resale.title.menu'))
@include('partials.page-fill')

@section('content')
    <x-index-page overflow="clip" :title="__('resale.title.subscriptions')" :subtitle="__('resale.subtitle')">
        <x-slot:actions>
            @can(\App\Enums\User\Permission::ResellingManage->value)
                <x-icon-btn icon="upload" tone="ghost" size="sm" data-entry-modal-trigger
                            :href="route('finance.resale.import.create')"
                            show-label>{{ __('resale.import.action') }}</x-icon-btn>
                <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger placement="bar"
                            :href="route('finance.resale.create', array_filter(['customer' => $filterCustomer?->sqid]))"
                            show-label>{{ __('resale.action.new') }}</x-icon-btn>
                {{-- Einstellungen (Feature 185): Abrechnung als Dialog statt Kasten in der Produkt-Einstufung. --}}
                <x-action-menu icon="settings" :label="__('resale.settings.menu')">
                    <x-icon-btn icon="event_repeat" tone="ghost" size="sm" data-entry-modal-trigger :href="route('finance.resale.settings.billing')" show-label>{{ __('resale.auto_draft.dialog_title') }}</x-icon-btn>
                    <x-icon-btn icon="inventory_2" tone="ghost" size="sm" :href="route('finance.resale.products')" show-label>{{ __('resale.products.title') }}</x-icon-btn>
                </x-action-menu>
            @endcan
        </x-slot:actions>

        @include('finance.resale._tabs')

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <x-kpi-tile :label="__('resale.summary.active')" :value="$summary['active']" />
            <x-kpi-tile :label="__('resale.summary.open_periods')" :value="$summary['open_periods']" :tone="$summary['open_periods'] > 0 ? 'warning' : 'success'" />
            <x-kpi-tile :label="__('resale.summary.unassigned')" :value="$summary['unassigned']" :tone="$summary['unassigned'] > 0 ? 'warning' : 'neutral'"
                        :href="route('finance.resale.subscriptions', ['unassigned' => 1])" :active="$filters['unassigned']" />
        </div>

        <x-filter-bar :action="route('finance.resale.subscriptions')" :reset="route('finance.resale.subscriptions')">
            <input type="search" name="q" value="{{ $filters['q'] }}" class="input input-sm input-bordered w-48"
                   placeholder="{{ __('resale.filter.search') }}" aria-label="{{ __('resale.filter.search') }}">
            <select name="kind" class="select select-sm select-bordered w-40" aria-label="{{ __('resale.field.kind') }}">
                <option value="">{{ __('resale.filter.all_kinds') }}</option>
                @foreach ($kinds as $kind)
                    <option value="{{ $kind->value }}" @selected($filters['kind'] === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </select>
            <select name="provider" class="select select-sm select-bordered w-44" aria-label="{{ __('resale.field.provider') }}">
                <option value="">{{ __('resale.filter.all_providers') }}</option>
                @foreach ($providers as $provider)
                    <option value="{{ $provider->value }}" @selected($filters['provider'] === $provider->value)>{{ $provider->label() }}</option>
                @endforeach
            </select>
            <select name="status" class="select select-sm select-bordered w-40" aria-label="{{ __('resale.field.status') }}">
                <option value="">{{ __('resale.filter.planning') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            @if ($filterCustomer !== null)
                <input type="hidden" name="customer" value="{{ $filterCustomer->sqid }}">
                <x-status-badge tone="plain" outline>{{ $filterCustomer->name }}</x-status-badge>
            @endif
            <x-filter-toggle name="open" :label="__('resale.filter.open_only')" :checked="$filters['open']" tone="warning" />
            <x-filter-toggle name="unassigned" :label="__('resale.filter.unassigned_only')" :checked="$filters['unassigned']" tone="warning" />
        </x-filter-bar>

        {{-- Sammelzuordnung des Halters (Nachbesserung 2026-10-27): Auswahl → ein Dialog für alle; die Toolbar-Form
             bekommt die gewählten ids[] vom Skript, der Dialog-Link trägt sie als Query (bulk-selection.js). --}}
        <div data-bulk-form class="contents">
        @if ($canManage)
            <form method="POST" action="{{ route('finance.resale.subscriptions.bulk.assign') }}" data-bulk-ids-form class="contents">
                @csrf
                <x-bulk-toolbar :label="__('resale.bulk.selected_subscriptions')">
                    <x-slot:actions>
                        <x-button :href="route('finance.resale.subscriptions.bulk.assign.create')" tone="primary" size="sm" icon="person_add" data-entry-modal-trigger data-bulk-dialog-link>{{ __('resale.bulk.assign') }}</x-button>
                    </x-slot:actions>
                </x-bulk-toolbar>
            </form>
        @endif
        <x-table scroll="flex" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    @if ($canManage)
                        <th class="w-8"><input type="checkbox" class="checkbox checkbox-sm" data-bulk-select-all aria-label="{{ __('Alle auswählen') }}"></th>
                    @endif
                    <x-table.th sort type="string">{{ __('resale.field.label') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('resale.field.holder') }}</x-table.th>
                    <x-table.th>{{ __('resale.field.kind') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('resale.field.provider') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.field.quantity') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('resale.field.starts_on') }}</x-table.th>
                    <x-table.th>{{ __('resale.field.interval') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.field.sale_unit_price') }}</x-table.th>
                    <x-table.th>{{ __('resale.field.status') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.field.open_periods') }}</x-table.th>
                    <x-table.th class="text-right"></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($subscriptions as $subscription)
                <tr class="hover">
                    @if ($canManage)
                        <td><input type="checkbox" class="checkbox checkbox-sm" data-bulk-checkbox name="ids[]" value="{{ $subscription->sqid }}" aria-label="{{ $subscription->label }}"></td>
                    @endif
                    <td>
                        <a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover font-medium">{{ $subscription->label }}</a>
                        @if ($subscription->productLabel() !== null)
                            <span class="block text-xs text-muted">{{ $subscription->productLabel() }}</span>
                        @endif
                    </td>
                    <td>
                        @if (! $subscription->hasHolder())
                            <x-status-badge size="xs" tone="warning" :label="__('resale.holder.unassigned')" />
                            {{-- Firma laut Anbieter als Anhaltspunkt für die Zuordnung (Nachbesserung 2026-10-27). --}}
                            @if (trim((string) $subscription->company_name) !== '')
                                <span class="block text-xs text-muted">{{ $subscription->company_name }}</span>
                            @endif
                        @else
                            {{ $subscription->holderLabel() }}
                            @if ($subscription->foreignCustomer !== null)
                                <span class="block text-xs text-muted">{{ __('resale.holder.via', ['partner' => $subscription->foreignCustomer->customer?->name]) }}</span>
                            @endif
                        @endif
                    </td>
                    <td><x-icon :name="$subscription->kind->icon()" size="1.1rem" /> <span class="text-sm">{{ $subscription->kind->label() }}</span></td>
                    <td class="text-sm">{{ $subscription->provider->label() }}</td>
                    <td class="text-right tabular-nums">{{ $subscription->quantity }}</td>
                    <td class="whitespace-nowrap tabular-nums">{{ $subscription->starts_on->fdate() }}@if ($subscription->ends_on) – {{ $subscription->ends_on->fdate() }}@endif</td>
                    <td class="text-sm">{{ $subscription->interval->label() }}</td>
                    <td class="text-right tabular-nums whitespace-nowrap">{{ $subscription->sale_unit_price?->format() ?? '—' }}</td>
                    <td><x-status-badge size="xs" :tone="$subscription->status->tone()" :label="$subscription->status->label()" /></td>
                    <td class="text-right tabular-nums">
                        {{-- Eigener Bestand wird nie berechnet: keine „offenen" Perioden (B14). --}}
                        @if ($subscription->is_own_holding)
                            <span class="text-muted" title="{{ __('resale.holder.own') }}">—</span>
                        @elseif ($subscription->open_periods_count > 0)
                            <x-status-badge tone="error">{{ $subscription->open_periods_count }}</x-status-badge>
                        @else
                            <span class="text-muted">0</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="visibility" size="xs" tone="ghost" :href="route('finance.resale.show', $subscription->sqid)" :title="__('resale.action.show')" />
                            @if ($canManage)
                                @if (! $subscription->hasHolder())
                                    <x-icon-btn icon="person_add" size="xs" tone="primary" data-entry-modal-trigger :href="route('finance.resale.subscriptions.bulk.assign.create', ['ids' => [$subscription->sqid]])" :title="__('resale.inbox.assign')" />
                                @endif
                                <x-icon-btn icon="edit" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.edit', $subscription->sqid)" :title="__('resale.action.edit')" />
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="$canManage ? 12 : 11" icon="subscriptions" :title="__('resale.empty.subscriptions')" compact />
            @endforelse
        </x-table>
        </div>
        <x-pagination :paginator="$subscriptions" standing />
    </x-index-page>
@endsection
