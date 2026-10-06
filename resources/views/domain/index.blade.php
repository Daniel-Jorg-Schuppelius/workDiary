{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('domain.title.index') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('domain.title.index'))
@include('partials.page-fill')

@section('content')
@php
    $availability = session('availability');
    $availability = is_array($availability) ? $availability : null;
    // Erster freier Treffer füllt den Registrier-Dialog vor.
    $availableDomain = collect($availability ?? [])->firstWhere('available', true)['domain'] ?? null;
@endphp
<x-index-page overflow="clip" :subtitle="__('domain.title.index_subtitle')">
    <x-slot:actions>
        @if ($registerConnections->isNotEmpty())
            <x-icon-btn icon="travel_explore" tone="outline" size="sm" data-open-dialog="domain-availability" show-label>{{ __('domain.action.check_availability') }}</x-icon-btn>
            <x-icon-btn icon="add" tone="primary" size="sm" data-open-dialog="domain-register" show-label>{{ __('domain.action.register') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @include('domain._tabs')

    @if ($availability !== null)
        <x-card :title="__('domain.availability.results')" icon="travel_explore" padding="p-0">
            <x-table bare :zebra="false" :caption="__('domain.availability.results')">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('domain.field.domain') }}</x-table.th>
                        <x-table.th>{{ __('domain.availability.state') }}</x-table.th>
                        <x-table.th align="right">{{ __('domain.availability.price') }}</x-table.th>
                        <x-table.th>{{ __('domain.availability.note') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($availability as $result)
                    <tr>
                        <td class="font-medium">{{ $result['domain'] ?? '' }}</td>
                        <td>
                            <x-status-badge :tone="($result['available'] ?? false) ? 'success' : 'neutral'">{{ ($result['available'] ?? false) ? __('domain.availability.available') : __('domain.availability.taken') }}</x-status-badge>
                            @if ($result['premium'] ?? false)
                                <x-status-badge tone="warning" outline>{{ __('domain.availability.premium') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ isset($result['price']) ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $result['price'], 2, withThousandsSeparator: true) . ' ' . ($result['currency'] ?? '') : '—' }}</td>
                        <td class="text-sm text-muted">{{ $result['class'] ?? '—' }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" :title="__('domain.availability.empty')" compact />
                @endforelse
            </x-table>
        </x-card>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('domain.metric.expiring_90')" :value="$metrics['expiring_90']" />
        <x-kpi-tile :label="__('domain.metric.risky')" :value="$metrics['risky']" />
        <x-kpi-tile :label="__('domain.metric.unmapped')" :value="$metrics['unmapped']" />
        <x-kpi-tile :label="__('domain.metric.sync_issues')" :value="$metrics['sync_issues']" />
    </div>

    <x-filter-bar :action="route('domains.index')" :reset="route('domains.index')">
        <x-filter-field :label="__('domain.filter.search')" for="dom-q" class="shrink-0">
            <input id="dom-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input input-sm input-bordered w-56"
                   placeholder="{{ __('domain.filter.search') }}" aria-label="{{ __('domain.filter.search') }}">
        </x-filter-field>
        <x-filter-field :label="__('domain.filter.tld')" for="dom-tld" class="shrink-0">
            <input id="dom-tld" type="text" name="tld" value="{{ $filters['tld'] ?? '' }}" class="input input-sm input-bordered w-28"
                   placeholder="{{ __('domain.filter.tld') }}" aria-label="{{ __('domain.filter.tld') }}">
        </x-filter-field>
        <x-filter-field :label="__('domain.field.status')" for="dom-sync" class="shrink-0">
            <select id="dom-sync" name="sync" class="select select-sm select-bordered w-40" aria-label="{{ __('domain.field.status') }}">
                <option value="">{{ __('domain.filter.all_sync') }}</option>
                @foreach (\App\Enums\Domain\DomainSyncStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(($filters['sync'] ?? '') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('domain.field.renewal_mode')" for="dom-renewal" class="shrink-0">
            <select id="dom-renewal" name="renewal_mode" class="select select-sm select-bordered w-44" aria-label="{{ __('domain.field.renewal_mode') }}">
                <option value="">{{ __('domain.filter.all_renewal') }}</option>
                @foreach (\App\Enums\Domain\DomainRenewalMode::cases() as $m)
                    <option value="{{ $m->value }}" @selected(($filters['renewal_mode'] ?? '') === $m->value)>{{ $m->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('domain.filter.expiry')" for="dom-expiry" class="shrink-0">
            <select id="dom-expiry" name="expiry_within" class="select select-sm select-bordered w-40" aria-label="{{ __('domain.filter.expiry') }}">
                <option value="">{{ __('domain.filter.expiry') }}</option>
                @foreach ([30, 60, 90, 180] as $d)
                    <option value="{{ $d }}" @selected((string) ($filters['expiry_within'] ?? '') === (string) $d)>{{ __('domain.filter.expiry_days', ['days' => $d]) }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex" :caption="__('domain.title.index')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('domain.field.domain') }}</x-table.th>
                <x-table.th>{{ __('domain.field.customer') }}</x-table.th>
                <x-table.th>{{ __('domain.field.expiration') }}</x-table.th>
                <x-table.th>{{ __('domain.field.renewal_mode') }}</x-table.th>
                <x-table.th>{{ __('domain.field.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($domains as $domain)
            <tr class="hover">
                <td>
                    <a href="{{ route('domains.show', $domain) }}" class="link link-hover font-medium">{{ $domain->external_domain }}</a>
                    <div class="text-xs text-muted font-mono">{{ $domain->external_user }}</div>
                </td>
                <td>{{ $domain->customer?->name ?? '—' }}</td>
                <td class="tabular-nums">{{ $domain->expiration_at?->fdate() ?? '—' }}</td>
                <td>{{ $domain->renewal_mode?->label() ?? '—' }}</td>
                <td><x-status-badge :tone="$domain->sync_status->badge()" size="sm">{{ $domain->sync_status->label() }}</x-status-badge></td>
            </tr>
        @empty
            <x-table.empty :colspan="5" :title="__('domain.empty.domains')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$domains" standing />

    @if ($registerConnections->isNotEmpty())
        <x-modal id="domain-availability" :embedded="false" icon="travel_explore"
                 :eyebrow="__('domain.title.index')" :title="__('domain.action.check_availability')"
                 :action="route('domains.availability')"
                 :submit-label="__('domain.action.check_availability')">
            <x-select-field name="connection" id="domain-availability-connection" :label="__('domain.field.connection')" required>
                @foreach ($registerConnections as $connection)
                    <option value="{{ $connection->sqid }}" @selected(old('connection', session('availability_connection')) === $connection->sqid)>{{ $connection->name }}</option>
                @endforeach
            </x-select-field>
            <x-textarea-field name="domains" id="domain-availability-domains" rows="4" required maxlength="2000"
                              :label="__('domain.field.domains')" :value="old('domains')"
                              :hint="__('domain.field.domains_help')" />
        </x-modal>

        <x-modal id="domain-register" :embedded="false" size="wide" icon="add_circle"
                 :eyebrow="__('domain.title.index')" :title="__('domain.action.register')"
                 :action="route('domains.register')"
                 :submit-label="__('domain.action.register')">
            <p class="text-sm text-base-content/70">{{ __('domain.register.hint') }}</p>
            <x-form-group :legend="__('domain.register.legend_domain')" icon="dns" tone="primary" cols="2">
                <x-select-field name="connection" id="domain-register-connection" :label="__('domain.field.connection')" required>
                    @foreach ($registerConnections as $connection)
                        <option value="{{ $connection->sqid }}" @selected(old('connection', session('availability_connection')) === $connection->sqid)>{{ $connection->name }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="domain" id="domain-register-domain" :label="__('domain.field.domain')" required maxlength="253"
                               :value="old('domain', $availableDomain)" />
                <x-select-field name="customer" id="domain-register-customer" :label="__('domain.field.customer')" required>
                    <option value="">—</option>
                    @foreach ($registerCustomers as $registerCustomer)
                        <option value="{{ $registerCustomer->sqid }}" @selected(old('customer') === $registerCustomer->sqid)>{{ $registerCustomer->name }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="cost_center" id="domain-register-cost-center" :label="__('domain.field.cost_center')" maxlength="64"
                               :value="old('cost_center')" />
                <x-input-field name="period" id="domain-register-period" type="number" min="1" max="10"
                               :label="__('domain.field.period')" :value="old('period', 1)" />
                <x-select-field name="renewal_mode" id="domain-register-renewal-mode" :label="__('domain.field.renewal_mode')">
                    @foreach (\App\Enums\Domain\DomainRenewalMode::cases() as $m)
                        <option value="{{ $m->value }}" @selected(old('renewal_mode', \App\Enums\Domain\DomainRenewalMode::Autorenew->value) === $m->value)>{{ $m->label() }}</option>
                    @endforeach
                </x-select-field>
            </x-form-group>
            <x-form-group :legend="__('domain.register.legend_contacts')" icon="contacts" cols="2"
                          :description="__('domain.register.contacts_hint')">
                <x-input-field name="owner_contact" id="domain-register-owner-contact" :label="__('domain.field.owner_contact')" required maxlength="190"
                               :value="old('owner_contact')" />
                <x-input-field name="admin_contact" id="domain-register-admin-contact" :label="__('domain.field.admin_contact')" maxlength="190"
                               :value="old('admin_contact')" />
                <x-input-field name="tech_contact" id="domain-register-tech-contact" :label="__('domain.field.tech_contact')" maxlength="190"
                               :value="old('tech_contact')" />
                <x-input-field name="billing_contact" id="domain-register-billing-contact" :label="__('domain.field.billing_contact')" maxlength="190"
                               :value="old('billing_contact')" />
                <x-textarea-field name="nameservers" id="domain-register-nameservers" rows="3" required maxlength="1000" span="2"
                                  :label="__('domain.field.nameservers')" :value="old('nameservers')"
                                  :hint="__('domain.field.nameservers_help')" />
            </x-form-group>
            <x-checkbox-field name="price_confirmed" id="domain-register-price-confirmed" :toggle="false" required
                              :label="__('domain.field.price_confirmed')" />
        </x-modal>
    @endif
</x-index-page>
@endsection
