{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : periods.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abrechnungsperioden (Feature 152, MVP-761): was berechnet ist und was
  nicht — fällige Perioden aller Abos mit Bezügen, Vorschlägen und
  Entscheidungen. Standardfilter: Probleme (offen, teilweise, strittig).
--}}
@extends('layouts.app')
@section('title', __('resale.periods.title'))
@section('nav-title', __('resale.title.menu'))
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    $canManage = auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false;
@endphp

@section('content')
    <x-index-page overflow="clip" :title="__('resale.periods.title')" :subtitle="__('resale.periods.subtitle')">
        <x-slot:actions>
            @if ($canManage)
                <form method="POST" action="{{ route('finance.resale.periods.propose') }}">
                    @csrf
                    <x-icon-btn icon="auto_awesome" tone="primary" size="sm" type="submit" show-label>{{ __('resale.link.action.propose') }}</x-icon-btn>
                </form>
            @endif
            <x-icon-btn icon="download" tone="ghost" size="sm" :href="route('finance.resale.report.export')" show-label>{{ __('resale.export.action') }}</x-icon-btn>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('finance.resale.index')" show-label>{{ __('resale.action.back') }}</x-icon-btn>
        </x-slot:actions>

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-4">
            @foreach ($statuses as $status)
                <x-kpi-tile :label="$status->label()" :value="$counts[$status->value] ?? 0" :tone="($counts[$status->value] ?? 0) > 0 ? $status->tone() : 'neutral'"
                            :href="route('finance.resale.periods.index', ['status' => $status->value])" :active="$filters['status'] === $status->value" />
            @endforeach
        </div>

        <x-filter-bar :action="route('finance.resale.periods.index')" :reset="route('finance.resale.periods.index')">
            <input type="search" name="q" value="{{ $filters['q'] }}" class="input input-sm input-bordered w-48"
                   placeholder="{{ __('resale.filter.search') }}" aria-label="{{ __('resale.filter.search') }}">
            <select name="status" class="select select-sm select-bordered w-44" aria-label="{{ __('resale.field.status') }}">
                <option value="problems" @selected($filters['status'] === 'problems')>{{ __('resale.periods.filter_problems') }}</option>
                <option value="all" @selected($filters['status'] === 'all')>{{ __('resale.periods.filter_all') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            @if ($filterCustomer !== null)
                <input type="hidden" name="customer" value="{{ $filterCustomer->sqid }}">
                <span class="badge badge-outline badge-sm">{{ $filterCustomer->name }}</span>
            @endif
        </x-filter-bar>

        <x-table scroll="flex" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('resale.field.label') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('resale.field.holder') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('resale.field.period') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.field.quantity') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.field.expected_sale') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.link.covered') }}</x-table.th>
                    <x-table.th>{{ __('resale.link.links') }}</x-table.th>
                    <x-table.th>{{ __('resale.field.status') }}</x-table.th>
                    <x-table.th class="text-right"></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($periods as $period)
                @include('finance.resale._period_row', ['period' => $period, 'subscription' => $period->subscription, 'showSubscription' => true, 'canManage' => $canManage, 'today' => $today])
            @empty
                <x-table.empty :colspan="9" icon="task_alt" :title="__('resale.periods.empty')" compact />
            @endforelse
        </x-table>
        <x-pagination :paginator="$periods" standing />
    </x-index-page>
@endsection
