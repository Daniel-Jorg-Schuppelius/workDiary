{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Beitragskonten (Feature 159, MVP-849): Zahlungspflichtige im Debitorenstamm, offene Wechselvorschläge. --}}
@extends('layouts.app')
@section('title', __('club.fees.title.accounts'))
@section('nav-title', __('club.fees.title.accounts'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.fees.subtitle.accounts')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.fees.accounts.create')" show-label>{{ __('club.fees.action.create_account') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="play_circle" tone="outline" size="sm" :href="route('club.fees.runs.index')" show-label>{{ __('club.fees.title.runs') }}</x-icon-btn>
        <x-icon-btn icon="receipt_long" tone="outline" size="sm" :href="route('club.fees.claims.index')" show-label>{{ __('club.fees.title.claims') }}</x-icon-btn>
        <x-icon-btn icon="calculate" tone="ghost" size="sm" :href="route('club.fees.preview')" show-label>{{ __('club.fees.action.preview') }}</x-icon-btn>
        <x-icon-btn icon="payments" tone="ghost" size="sm" :href="route('club.fees.tariffs.index')" show-label>{{ __('club.fees.title.tariffs') }}</x-icon-btn>
        <x-help-button topic="club.fees" />
    </x-slot:actions>

    <x-filter-bar :action="route('club.fees.accounts.index')" :reset="route('club.fees.accounts.index')">
        <input type="search" name="q" value="{{ $filters['q'] }}" class="input input-sm input-bordered w-56 shrink-0" placeholder="{{ __('club.filter.search') }}" aria-label="{{ __('club.filter.search') }}">
    </x-filter-bar>

    @if ($review->isNotEmpty())
        <div class="alert alert-warning mb-3 text-sm" role="status">
            <x-icon name="rule" />
            <div>
                <p class="font-medium">{{ trans_choice('club.fees.review_pending', $review->count(), ['count' => $review->count()]) }}</p>
                <ul class="list-inside list-disc">
                    @foreach ($review as $assignment)
                        <li><a href="{{ route('club.fees.accounts.show', $assignment->account) }}" class="link">{{ $assignment->member?->fullName() }}</a> · {{ $assignment->tariff?->name }} — {{ $assignment->review_note }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.fees.field.account') }}</th>
                <th>{{ __('club.fees.field.customer') }}</th>
                <th>{{ __('club.field.email') }}</th>
                <th class="text-right">{{ __('club.fees.field.active_members') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($accounts as $account)
            <tr class="hover">
                <td class="font-medium"><a href="{{ route('club.fees.accounts.show', $account) }}" class="link link-hover">{{ $account->name }}</a></td>
                <td class="text-sm">{{ $account->customer?->name }}@if ($account->customer?->number) <span class="text-xs text-muted">· {{ $account->customer->number }}</span>@endif</td>
                <td class="text-sm text-muted">{{ $account->email ?? '–' }}</td>
                <td class="text-right tabular-nums">{{ $account->active_assignments_count }}</td>
                <td class="text-right"><x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('club.fees.accounts.show', $account)" :label="__('club.action.show')" /></td>
            </tr>
        @empty
            <x-table.empty icon="account_balance_wallet" :colspan="5" :title="__('club.fees.empty.accounts')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$accounts" standing />
</x-index-page>
@endsection
