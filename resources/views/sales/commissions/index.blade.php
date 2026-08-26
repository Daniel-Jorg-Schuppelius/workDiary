{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Provisionszeilen je Beleg (Feature 146). Oben der Arbeitsvorrat: bezahlte
  Rechnungen, denen niemand zugeordnet ist — sonst bliebe der häufigste
  Fehlerfall unsichtbar.
--}}
@extends('layouts.app')
@section('title', __('commission.title'))
@section('nav-title', __('commission.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('commission.subtitle.index')">
    <x-slot:actions>
        <x-icon-btn icon="percent" tone="ghost" size="sm"
                    :href="route('commission-rules.index')"
                    show-label>{{ __('commission.action.to_rules') }}</x-icon-btn>
        <x-icon-btn icon="event_repeat" tone="ghost" size="sm"
                    :href="route('commission-runs.index')"
                    show-label>{{ __('commission.action.to_runs') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('commissions.index')" :reset="route('commissions.index')">
        <x-filter-field :label="__('commission.field.status')" for="flt-status">
            <select id="flt-status" name="status" class="select select-bordered select-sm" data-autosubmit>
                <option value="">{{ __('Alle') }}</option>
                @foreach (\App\Enums\Sales\CommissionStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status?->value === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    @if ($unassigned->isNotEmpty())
        <x-card>
            <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                <x-icon name="person_alert" class="text-muted" /> {{ __('commission.section.unassigned') }}
            </h3>
            <p class="mb-3 text-xs text-muted">{{ __('commission.hint.unassigned') }}</p>
            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('commission.field.invoice') }}</th>
                        <th>{{ __('commission.field.customer') }}</th>
                        <th>{{ __('commission.field.paid_on') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($unassigned as $invoice)
                    <tr class="hover">
                        <td class="font-mono text-sm">{{ $invoice->number ?? $invoice->sqid }}</td>
                        <td class="text-sm">{{ $invoice->customer?->name ?? '–' }}</td>
                        <td class="text-sm">{{ $invoice->paid_on?->format('d.m.Y') ?? '–' }}</td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                @if ($canManage)
                                    <x-icon-btn icon="person_add" tone="outline" size="xs"
                                                data-entry-modal-trigger
                                                :href="route('commissions.assign.form', $invoice)"
                                                :label="__('commission.action.assign')" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="date" default>{{ __('commission.field.earned_on') }}</x-table.th>
                <x-table.th sort type="string">{{ __('commission.field.user') }}</x-table.th>
                <x-table.th sort type="string">{{ __('commission.field.invoice') }}</x-table.th>
                <x-table.th sort type="string">{{ __('commission.field.customer') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('commission.field.base_amount') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('commission.field.rate_percent') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('commission.field.commission_amount') }}</x-table.th>
                <x-table.th sort type="string" align="center">{{ __('commission.field.status') }}</x-table.th>
                <x-table.th sort type="string">{{ __('commission.field.run') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($commissions as $commission)
            <tr class="hover">
                <td class="text-sm">{{ $commission->earned_on?->format('d.m.Y') ?? '–' }}</td>
                <td class="text-sm font-medium">{{ $commission->user?->name ?? '–' }}</td>
                <td class="font-mono text-sm">
                    {{ $commission->invoice?->number ?? '–' }}
                    @if ($commission->isReversal())
                        <x-status-badge tone="error" size="sm" outline>{{ __('commission.badge.reversal') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-sm text-base-content/70">{{ $commission->invoice?->customer?->name ?? '–' }}</td>
                <td class="text-right font-mono text-sm">{{ $commission->base_amount?->format() ?? '–' }}</td>
                <td class="text-right font-mono text-sm">{{ $commission->rate_percent?->format() ?? '0,00' }} %</td>
                <td class="text-right font-mono text-sm">{{ $commission->commission_amount?->format() ?? '–' }}</td>
                <td class="text-center">
                    <x-status-badge :tone="$commission->status->tone()" size="sm">{{ $commission->status->label() }}</x-status-badge>
                </td>
                <td class="text-sm">{{ $commission->settlementRun?->period ?? '–' }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        @if ($canManage && $commission->invoice !== null)
                            <x-icon-btn icon="person_add" tone="ghost" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('commissions.assign.form', $commission->invoice)"
                                        :label="__('commission.action.assign')" />
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="percent" :colspan="10" :title="__('commission.empty.commissions')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$commissions" standing />
</x-index-page>
@endsection
