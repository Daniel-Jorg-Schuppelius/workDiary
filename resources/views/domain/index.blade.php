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

@section('content')
<x-index-page overflow="clip" :subtitle="__('domain.title.index_subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="settings_ethernet" size="sm" :href="route('admin.domain-provider.index')" show-label>{{ __('domain.title.connections') }}</x-icon-btn>
        <x-icon-btn icon="account_tree" size="sm" :href="route('domain-reseller.index')" show-label>{{ __('domain.title.reseller') }}</x-icon-btn>
        <x-icon-btn icon="analytics" size="sm" :href="route('domains.reports')" show-label>{{ __('domain.title.reports') }}</x-icon-btn>
    </x-slot:actions>

    @if (session('success'))
        <div role="alert" class="alert alert-success"><span>{{ session('success') }}</span></div>
    @endif
    @if (session('error'))
        <div role="alert" class="alert alert-error"><span>{{ session('error') }}</span></div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('domain.metric.expiring_90')" :value="$metrics['expiring_90']" />
        <x-kpi-tile :label="__('domain.metric.risky')" :value="$metrics['risky']" />
        <x-kpi-tile :label="__('domain.metric.unmapped')" :value="$metrics['unmapped']" />
        <x-kpi-tile :label="__('domain.metric.sync_issues')" :value="$metrics['sync_issues']" />
    </div>

    <x-filter-bar :action="route('domains.index')" :reset="route('domains.index')">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input input-sm input-bordered w-56 shrink-0"
               placeholder="{{ __('domain.filter.search') }}" aria-label="{{ __('domain.filter.search') }}">
        <input type="text" name="tld" value="{{ $filters['tld'] ?? '' }}" class="input input-sm input-bordered w-28 shrink-0"
               placeholder="{{ __('domain.filter.tld') }}" aria-label="{{ __('domain.filter.tld') }}">
        <select name="sync" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('domain.field.status') }}">
            <option value="">{{ __('domain.filter.all_sync') }}</option>
            @foreach (\App\Enums\Domain\DomainSyncStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(($filters['sync'] ?? '') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <select name="renewal_mode" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('domain.field.renewal_mode') }}">
            <option value="">{{ __('domain.filter.all_renewal') }}</option>
            @foreach (\App\Enums\Domain\DomainRenewalMode::cases() as $m)
                <option value="{{ $m->value }}" @selected(($filters['renewal_mode'] ?? '') === $m->value)>{{ $m->label() }}</option>
            @endforeach
        </select>
        <select name="expiry_within" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('domain.filter.expiry') }}">
            <option value="">{{ __('domain.filter.expiry') }}</option>
            @foreach ([30, 60, 90, 180] as $d)
                <option value="{{ $d }}" @selected((string) ($filters['expiry_within'] ?? '') === (string) $d)>{{ __('domain.filter.expiry_days', ['days' => $d]) }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('domain.field.domain') }}</th>
                    <th>{{ __('domain.field.customer') }}</th>
                    <th>{{ __('domain.field.expiration') }}</th>
                    <th>{{ __('domain.field.renewal_mode') }}</th>
                    <th>{{ __('domain.field.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($domains as $domain)
                    <tr>
                        <td>
                            <a href="{{ route('domains.show', $domain) }}" class="link link-hover font-medium">{{ $domain->external_domain }}</a>
                            <div class="text-xs text-base-content/60 font-mono">{{ $domain->external_user }}</div>
                        </td>
                        <td>{{ $domain->customer?->name ?? '—' }}</td>
                        <td class="tabular-nums">{{ $domain->expiration_at?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $domain->renewal_mode?->label() ?? '—' }}</td>
                        <td><span class="badge badge-{{ $domain->sync_status->badge() }} badge-sm">{{ $domain->sync_status->label() }}</span></td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" :title="__('domain.empty.domains')" compact />
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$domains" standing />
</x-index-page>
@endsection
