{{--
  Created on   : Sun Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Investitionen') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Investitionen'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Investitionsakten mit Varianten, Budgetantrag, Freigabe und Soll-Ist-Verfolgung.')">
    <x-slot:actions>
        @can(\App\Enums\User\Permission::InvestmentManage->value)
            <x-icon-btn icon="link" size="sm" data-entry-modal-trigger :href="route('investments.proposals.link')" show-label>{{ __('investment.proposal.link') }}</x-icon-btn>
        @endcan
        @can('create', \App\Models\Investments\InvestmentCase::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('investments.create')"
                        show-label>{{ __('Investition erfassen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    @include('investments._tabs')

    <x-filter-bar :action="route('investments.index')" :reset="route('investments.index')">
        <x-filter-field :label="__('Status')" for="inv-status" class="shrink-0">
            <select id="inv-status" name="status" class="select select-sm select-bordered w-44" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('Kategorie')" for="inv-category" class="shrink-0">
            <select id="inv-category" name="category" class="select select-sm select-bordered w-44" aria-label="{{ __('Kategorie') }}">
                <option value="">{{ __('Alle Kategorien') }}</option>
                @foreach ($categories as $c)
                    <option value="{{ $c }}" @selected(($filters['category'] ?? '') === $c)>{{ __("values.$c") }}</option>
                @endforeach
            </select>
        </x-filter-field>
        {{-- Zeitraum, Verantwortliche, Kostenstelle, Budgetstatus, Risiko (MVP-1606). --}}
        <x-date-range class="w-80 shrink-0" :label="false" from-name="from" to-name="to" type="date"
                      fromId="inv-from" toId="inv-to" :from="$filters['from']" :to="$filters['to']" />
        <x-filter-field :label="__('Verantwortlich')" for="inv-responsible" class="shrink-0">
            <select id="inv-responsible" name="responsible" class="select select-sm select-bordered w-44" aria-label="{{ __('Verantwortlich') }}">
                <option value="">{{ __('investment.filter.all_responsible') }}</option>
                @foreach ($users as $user)
                    <option value="{{ $user->sqid }}" @selected($filters['responsible'] === $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
        @if ($costCenters->isNotEmpty())
            <x-filter-field :label="__('Kostenstelle')" for="inv-cost-center" class="shrink-0">
                <select id="inv-cost-center" name="cost_center" class="select select-sm select-bordered w-44" aria-label="{{ __('Kostenstelle') }}">
                    <option value="">{{ __('investment.filter.all_cost_centers') }}</option>
                    @foreach ($costCenters as $costCenter)
                        <option value="{{ $costCenter->sqid }}" @selected($filters['cost_center'] === $costCenter->id)>{{ $costCenter->code }} — {{ $costCenter->label }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
        <x-filter-field :label="__('investment.filter.budget')" for="inv-budget" class="shrink-0">
            <select id="inv-budget" name="budget" class="select select-sm select-bordered w-44" aria-label="{{ __('investment.filter.budget') }}">
                <option value="">{{ __('investment.filter.all_budget') }}</option>
                <option value="none" @selected($filters['budget'] === 'none')>{{ __('investment.filter.budget_none') }}</option>
                @foreach ($budgetStatuses as $budgetStatus)
                    <option value="{{ $budgetStatus->value }}" @selected($filters['budget'] === $budgetStatus->value)>{{ $budgetStatus->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('Dringlichkeit')" for="inv-urgency" class="shrink-0">
            <select id="inv-urgency" name="urgency" class="select select-sm select-bordered w-40" aria-label="{{ __('Dringlichkeit') }}">
                <option value="">{{ __('investment.filter.all_urgency') }}</option>
                @foreach (['high', 'medium', 'low'] as $urgency)
                    <option value="{{ $urgency }}" @selected($filters['urgency'] === $urgency)>{{ __("values.$urgency") }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-toggle name="risk" :label="__('investment.filter.with_risk')" :checked="$filters['risk']" />
    </x-filter-bar>

    <x-table scroll="flex" :pinRows="true" :zebra="true" size="sm" table-sort="server"
             :route="route('investments.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'desc'"
             :sort-params="request()->except(['sort', 'dir', 'page'])">
        <x-slot:head>
            <tr>
                <x-table.th sort="title">{{ __('Titel') }}</x-table.th>
                <x-table.th sort="category">{{ __('Kategorie') }}</x-table.th>
                <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                <x-table.th>{{ __('investment.filter.budget') }}</x-table.th>
                <x-table.th>{{ __('Kostenstelle') }}</x-table.th>
                <x-table.th>{{ __('Verantwortlich') }}</x-table.th>
                <x-table.th></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($cases as $case)
            <tr class="hover">
                <td><a href="{{ route('investments.show', $case) }}" class="link link-hover font-medium">{{ $case->title }}</a></td>
                <td>{{ __("values.{$case->category}") }}</td>
                <td><x-status-badge :tone="$case->status->tone()" size="sm">{{ $case->status->label() }}</x-status-badge></td>
                <td>
                    @php($latestBudget = $case->budgetRequests->first())
                    {{ $latestBudget !== null ? $latestBudget->status->label() : '—' }}
                    @if (filled($case->risk_note))
                        <x-status-badge tone="warning" size="xs" outline :title="$case->risk_note">{{ __('investment.filter.risk_badge') }}</x-status-badge>
                    @endif
                </td>
                <td>{{ $case->costCenterDisplay() ?? '—' }}</td>
                <td>{{ $case->responsible->name ?? '—' }}</td>
                <td class="text-right"><x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('investments.show', $case)" :label="__('Anzeigen')" /></td>
            </tr>
        @empty
            <x-table.empty icon="trending_up" :colspan="7" :title="__('Keine Investitionen — „Investition erfassen“ startet die erste Akte.')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$cases" standing />
</x-index-page>
@endsection
