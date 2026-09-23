{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Beitragstarife und Abteilungszuschläge (Feature 159, MVP-849): Tarife mit versionierten Sätzen. --}}
@extends('layouts.app')
@section('title', __('club.fees.title.tariffs'))
@section('nav-title', __('club.fees.title.tariffs'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('club.fees.subtitle.tariffs')">
            <x-slot:actions>
                @if ($canManage)
                    <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.fees.tariffs.create')" show-label>{{ __('club.fees.action.create_tariff') }}</x-icon-btn>
                    <x-icon-btn icon="add_circle" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.fees.surcharges.create')" show-label>{{ __('club.fees.action.create_surcharge') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="account_balance_wallet" tone="ghost" size="sm" :href="route('club.fees.accounts.index')" show-label>{{ __('club.fees.title.accounts') }}</x-icon-btn>
                <x-help-button topic="club.fees" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($errors->any())
        <div class="alert alert-error text-sm" role="alert">
            <x-icon name="error" />
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.fees.card.tariffs')" icon="payments" :count="$tariffs->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.fees.hint.tariffs') }}</p>
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.fees.field.tariff') }}</th>
                            <th>{{ __('club.fees.field.kind') }}</th>
                            <th>{{ __('club.fees.field.rates') }}</th>
                            <th class="text-right">{{ __('club.fees.field.assignments') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($tariffs as $tariff)
                        <tr class="align-top">
                            <td>
                                <span class="font-medium">{{ $tariff->name }}</span>
                                @unless ($tariff->is_active)<x-status-badge tone="ghost" size="xs" :label="__('club.label.inactive')" />@endunless
                                @if ($tariff->hasAgeCriteria())<span class="block text-xs text-muted">{{ __('club.field.age_range') }}: {{ $tariff->min_age ?? '–' }}–{{ $tariff->max_age ?? '–' }}</span>@endif
                                @if ($tariff->description)<span class="block text-xs text-muted">{{ $tariff->description }}</span>@endif
                            </td>
                            <td><x-status-badge :tone="$tariff->isFamily() ? 'info' : 'ghost'" size="sm">{{ $tariff->kind->label() }}</x-status-badge></td>
                            <td class="text-xs">
                                <ul class="space-y-0.5">
                                    @forelse ($tariff->rates as $rate)
                                        <li class="flex flex-wrap items-center gap-1">
                                            <span class="tabular-nums">{{ __('club.fees.label.from', ['date' => $rate->valid_from->format('d.m.Y')]) }}</span>
                                            <strong class="tabular-nums">{{ $rate->amount->format() }}</strong>
                                            <span>{{ $rate->interval->label() }}</span>
                                            <span class="text-muted">· {{ $rate->proration->label() }} · {{ __('club.fees.label.due_days', ['days' => $rate->due_days]) }}</span>
                                            @if ($rate->interval !== \App\Enums\Finance\RecurringInterval::Monthly)<span class="text-muted">· {{ __('club.fees.label.anchor', ['month' => $rate->anchor_month]) }}</span>@endif
                                            @if ($rate->admission_fee && ! $rate->admission_fee->isZero())<span class="text-muted">· {{ __('club.fees.label.admission_fee') }} {{ $rate->admission_fee->format() }}</span>@endif
                                            @if ($canManage)
                                                <x-icon-btn icon="edit" tone="ghost" size="xs" data-entry-modal-trigger :href="route('club.fees.rates.edit', $rate)" :label="__('club.action.edit')" />
                                                @if ($tariff->rates->count() > 1)
                                                    <x-action-form :action="route('club.fees.rates.destroy', $rate)" method="DELETE" :confirm="__('club.fees.confirm.delete_rate')" confirm-icon="delete" confirm-tone="error">
                                                        <x-icon-btn type="submit" icon="delete" tone="ghost" size="xs" :label="__('club.action.delete')" />
                                                    </x-action-form>
                                                @endif
                                            @endif
                                        </li>
                                    @empty
                                        <li class="text-warning">{{ __('club.fees.label.no_rate') }}</li>
                                    @endforelse
                                </ul>
                            </td>
                            <td class="text-right tabular-nums">{{ $tariff->assignments_count }}</td>
                            <td class="text-right">
                                @if ($canManage)
                                    <div class="flex justify-end gap-1">
                                        <x-icon-btn icon="add" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.fees.rates.create', $tariff)" :label="__('club.fees.action.create_rate')" />
                                        <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.fees.tariffs.edit', $tariff)" :label="__('club.action.edit')" />
                                        @if ($tariff->assignments_count === 0)
                                            <x-action-form :action="route('club.fees.tariffs.destroy', $tariff)" method="DELETE" :confirm="__('club.fees.confirm.delete_tariff')" confirm-icon="delete" confirm-tone="error">
                                                <x-icon-btn type="submit" icon="delete" tone="error" size="xs" :label="__('club.action.delete')" />
                                            </x-action-form>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="payments" :colspan="5" :title="__('club.fees.empty.tariffs')" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.fees.card.surcharges')" icon="add_circle" :count="$surcharges->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.fees.hint.surcharges') }}</p>
                <ul class="space-y-1 text-sm">
                    @forelse ($surcharges as $surcharge)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $surcharge->name }}</span>
                            <span class="text-xs text-muted">{{ $surcharge->department?->name }}</span>
                            <span class="tabular-nums">{{ $surcharge->amount->format() }} {{ $surcharge->interval->label() }}</span>
                            <span class="text-xs text-muted">{{ $surcharge->valid_from->format('d.m.Y') }}@if ($surcharge->valid_to) – {{ $surcharge->valid_to->format('d.m.Y') }}@endif</span>
                            @if ($canManage)
                                <span class="ml-auto flex gap-1">
                                    <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.fees.surcharges.edit', $surcharge)" :label="__('club.action.edit')" />
                                    <x-action-form :action="route('club.fees.surcharges.destroy', $surcharge)" method="DELETE" :confirm="__('club.fees.confirm.delete_surcharge')" confirm-icon="delete" confirm-tone="error">
                                        <x-icon-btn type="submit" icon="delete" tone="error" size="xs" :label="__('club.action.delete')" />
                                    </x-action-form>
                                </span>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.fees.empty.surcharges') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
