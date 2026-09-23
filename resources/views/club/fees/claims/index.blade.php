{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Beitragsforderungen / offene Posten (MVP-850): Status, Fälligkeit, Restbetrag. --}}
@extends('layouts.app')
@section('title', __('club.fees.title.claims'))
@section('nav-title', __('club.fees.title.claims'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.fees.subtitle.claims')">
    <x-slot:actions>
        <x-icon-btn icon="account_balance" tone="outline" size="sm" :href="route('club.fees.collections.index')" show-label>{{ __('club.fees.title.collections') }}</x-icon-btn>
        <x-icon-btn icon="play_circle" tone="outline" size="sm" :href="route('club.fees.runs.index')" show-label>{{ __('club.fees.title.runs') }}</x-icon-btn>
        <x-icon-btn icon="account_balance_wallet" tone="ghost" size="sm" :href="route('club.fees.accounts.index')" show-label>{{ __('club.fees.title.accounts') }}</x-icon-btn>
        <x-help-button topic="club.fees" />
    </x-slot:actions>

    <x-filter-bar :action="route('club.fees.claims.index')" :reset="route('club.fees.claims.index')">
        <input type="search" name="q" value="{{ $filters['q'] }}" class="input input-sm input-bordered w-48 shrink-0" placeholder="{{ __('club.filter.search') }}" aria-label="{{ __('club.filter.search') }}">
        <x-filter-field :label="__('club.field.status')" for="flt-status">
            <select id="flt-status" name="status" class="select select-sm select-bordered w-48 shrink-0" data-autosubmit>
                <option value="open" @selected($filters['status'] === 'open')>{{ __('club.fees.filter.open') }}</option>
                <option value="overdue" @selected($filters['status'] === 'overdue')>{{ __('club.fees.filter.overdue') }}</option>
                <option value="all" @selected($filters['status'] === 'all')>{{ __('club.filter.all_statuses') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        @if ($filters['account'] !== '')
            <input type="hidden" name="account" value="{{ $filters['account'] }}">
        @endif
        <span class="ml-auto text-xs text-muted">
            @if ($openTotal){{ __('club.fees.label.open_total', ['total' => $openTotal->format()]) }}@endif
            @if ($overdueCount > 0) · {{ trans_choice('club.fees.overdue_count', $overdueCount, ['count' => $overdueCount]) }}@endif
        </span>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.fees.field.number') }}</th>
                <th>{{ __('club.fees.field.account') }}</th>
                <th>{{ __('club.field.period') }}</th>
                <th>{{ __('club.fees.field.due_on') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th class="text-right">{{ __('club.fees.field.amount') }}</th>
                <th class="text-right">{{ __('club.fees.field.open_amount') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($claims as $claim)
            <tr class="hover">
                <td class="font-medium">
                    <a href="{{ route('club.fees.claims.show', $claim) }}" class="link link-hover">{{ $claim->number }}</a>
                    @if ($claim->isCorrection())<x-status-badge tone="info" size="xs" :label="__('club.fees.label.correction_of', ['number' => $claim->correctedClaim?->number ?? ''])" />@endif
                </td>
                <td class="text-sm">{{ $claim->account?->name }}</td>
                <td class="whitespace-nowrap text-sm tabular-nums">{{ $claim->period_start->format('d.m.Y') }} – {{ $claim->period_end->format('d.m.Y') }}</td>
                <td class="whitespace-nowrap text-sm tabular-nums {{ $claim->isOverdue($today) ? 'text-error font-medium' : '' }}">{{ $claim->due_on->format('d.m.Y') }}</td>
                <td>
                    <x-status-badge :tone="$claim->status->tone()" size="sm">{{ $claim->status->label() }}</x-status-badge>
                    @if ($claim->isOverdue($today))<x-status-badge tone="error" size="xs" :label="__('club.fees.label.overdue')" />@endif
                </td>
                <td class="text-right tabular-nums">{{ $claim->total->format() }}</td>
                <td class="text-right tabular-nums">{{ $claim->openAmount()->format() }}</td>
                <td class="text-right"><x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('club.fees.claims.show', $claim)" :label="__('club.action.show')" /></td>
            </tr>
        @empty
            <x-table.empty icon="receipt_long" :colspan="8" :title="__('club.fees.empty.claims')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$claims" standing />
</x-index-page>
@endsection
