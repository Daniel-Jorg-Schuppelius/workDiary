{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Spenden und Zuwendungsbestätigungen eines Jahres (Feature 159, MVP-1003).
  Variablen: $year, $donations, $receipts, $openByDonor, $exemptionComplete, $canManage
--}}
@extends('layouts.app')
@section('title', __('club.donations.title'))
@section('nav-title', __('club.donations.title'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('club.donations.subtitle', ['year' => $year])">
            <x-slot:actions>
                @if ($canManage)
                    <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.fees.donations.create')" show-label>{{ __('club.donations.action.add') }}</x-icon-btn>
                    <x-icon-btn icon="settings" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.settings.edit')" show-label>{{ __('club.donations.action.settings') }}</x-icon-btn>
                @endif
                <x-help-button topic="club.fees" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('club.fees._tabs')

    <x-filter-bar :action="route('club.fees.donations.index')" :reset="$year !== (int) now()->year ? route('club.fees.donations.index') : null">
        <x-filter-field :label="__('club.donations.field.year')" for="don-year" inline>
            <select id="don-year" name="year" class="select select-sm select-bordered" data-autosubmit>
                @for ($y = (int) now()->year + 1; $y >= (int) now()->year - 10; $y--)
                    <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                @endfor
            </select>
        </x-filter-field>
    </x-filter-bar>

    @if ($errors->any())
        <div class="alert alert-error text-sm" role="alert"><x-icon name="error" /><span>{{ $errors->first() }}</span></div>
    @endif
    @unless ($exemptionComplete)
        <div class="alert alert-warning text-sm" role="status"><x-icon name="warning" /><span>{{ __('club.donations.hint.exemption_missing') }}</span></div>
    @endunless

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.donations.card.donations')" icon="volunteer_activism" :count="$donations->count()">
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.donations.field.received_on') }}</th>
                            <th>{{ __('club.donations.field.donor') }}</th>
                            <th>{{ __('club.donations.field.kind') }}</th>
                            <th class="text-right">{{ __('club.donations.field.amount') }}</th>
                            <th>{{ __('club.donations.field.receipt') }}</th>
                            <th class="text-right"></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($donations as $donation)
                        <tr>
                            <td class="whitespace-nowrap">{{ $donation->received_on->format('d.m.Y') }}</td>
                            <td>{{ $donation->donorLabel() }}@if ($donation->is_expense_waiver)<x-status-badge tone="ghost" size="xs" class="ml-1">{{ __('club.donations.field.expense_waiver_short') }}</x-status-badge>@endif</td>
                            <td>{{ $donation->kind->label() }}</td>
                            <td class="text-right tabular-nums">{{ $donation->amount->format() }}</td>
                            <td>{{ $donation->receipt?->displayNo() ?? '—' }}</td>
                            <td class="text-right whitespace-nowrap">
                                @if ($canManage && ! $donation->isReceipted())
                                    <x-action-form :action="route('club.fees.donations.issue', $donation)">
                                        <x-icon-btn icon="verified" size="sm" type="submit" :label="__('club.donations.action.issue')" />
                                    </x-action-form>
                                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('club.fees.donations.edit', $donation)" :label="__('club.donations.action.edit')" />
                                    <x-action-form :action="route('club.fees.donations.destroy', $donation)" method="DELETE" :confirm="__('club.donations.confirm.delete')" confirm-tone="error" confirm-icon="delete" :confirm-label="__('Löschen')">
                                        <x-icon-btn icon="delete" tone="error" size="sm" type="submit" :label="__('Löschen')" />
                                    </x-action-form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="6" :title="__('club.donations.empty')" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <div class="space-y-4">
            @if ($canManage && $openByDonor->isNotEmpty())
                <x-card :title="__('club.donations.card.collective')" icon="summarize">
                    <p class="mb-2 text-xs text-muted">{{ __('club.donations.hint.collective') }}</p>
                    <ul class="divide-y divide-base-300 text-sm">
                        @foreach ($openByDonor as $key => $rows)
                            <li class="flex items-center justify-between gap-2 py-2">
                                <span>{{ $rows->first()->donorLabel() }} <span class="text-muted">· {{ trans_choice('club.donations.count', $rows->count(), ['count' => $rows->count()]) }}</span></span>
                                <x-action-form :action="route('club.fees.donations.collective')">
                                    <input type="hidden" name="donor" value="{{ $key }}">
                                    <input type="hidden" name="year" value="{{ $year }}">
                                    <x-icon-btn icon="summarize" size="sm" tone="outline" type="submit" show-label>{{ __('club.donations.action.collective') }}</x-icon-btn>
                                </x-action-form>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card :title="__('club.donations.card.receipts')" icon="verified" :count="$receipts->count()">
                @if ($receipts->isEmpty())
                    <p class="text-sm text-muted">{{ __('club.donations.no_receipts') }}</p>
                @else
                    <ul class="divide-y divide-base-300 text-sm">
                        @foreach ($receipts as $receipt)
                            <li class="flex items-center justify-between gap-2 py-2">
                                <span><span class="font-medium">{{ $receipt->displayNo() }}</span> · {{ $receipt->donor_snapshot['name'] ?? '' }}
                                    <span class="block text-xs text-muted">{{ $receipt->kind->label() }} · {{ $receipt->total_amount->format() }} · {{ $receipt->issued_on->format('d.m.Y') }}</span></span>
                                <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('club.fees.donations.receipts.pdf', $receipt)" :label="__('club.donations.action.pdf')" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
