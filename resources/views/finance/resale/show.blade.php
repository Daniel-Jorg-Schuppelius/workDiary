{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abo-Detail (Feature 152, MVP-758): Stammdaten, Halter, Preise und die
  geplanten Abrechnungsperioden mit Status. Rechnungsbezüge und
  Entscheidungen je Periode kommen mit MVP-761. Rechnungsliste und
  Schnellzuordnung sind im Controller vorbereitet ($invoices, $openPeriods).
--}}
@extends('layouts.app')
@section('title', $subscription->label)
@section('nav-title', __('resale.title.menu'))

@php
    $billedTo = $subscription->billedTo();
    $openCount = $subscription->openPeriodCount();
    $canManage = auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false;
@endphp

@section('content')
    <x-index-page :title="$subscription->label" :subtitle="$subscription->kind->label() . ' · ' . $subscription->provider->label()"
                  back-route="finance.resale.index" :back-label="__('resale.action.back')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="edit" tone="primary" size="sm" data-entry-modal-trigger
                            :href="route('finance.resale.edit', $subscription->sqid)"
                            show-label>{{ __('resale.action.edit') }}</x-icon-btn>
                <form method="POST" action="{{ route('finance.resale.destroy', $subscription->sqid) }}" data-confirm-dialog data-confirm-message="{{ __('resale.confirm.delete') }}" data-confirm-tone="error">
                    @csrf
                    @method('DELETE')
                    <x-icon-btn placement="danger" icon="delete" tone="ghost" size="sm" type="submit" show-label>{{ __('resale.action.delete') }}</x-icon-btn>
                </form>
            @endif
            @if ($canManage && ! $subscription->isAssignment() && $subscription->quantity > 1)
                <x-icon-btn icon="call_split" tone="ghost" size="sm" data-entry-modal-trigger
                            :href="route('finance.resale.transfer.create', $subscription->sqid)"
                            show-label>{{ __('resale.transfer.action') }}</x-icon-btn>
            @endif
            @if ($canManage && $subscription->hasHolder() && ! $subscription->is_own_holding)
                <form method="POST" action="{{ route('finance.resale.periods.propose') }}">
                    @csrf
                    <x-icon-btn icon="auto_awesome" tone="ghost" size="sm" type="submit" show-label>{{ __('resale.link.action.propose') }}</x-icon-btn>
                </form>
            @endif
        </x-slot:actions>

        {{-- Schnellzuordnung und Halterwechsel senden ohne Dialog: Feldfehler landen hier. --}}
        <x-validation-errors class="mb-4" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <x-card :title="__('resale.section.holder')">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('resale.field.holder')">
                        @if (! $subscription->hasHolder())
                            <x-status-badge size="xs" tone="warning" :label="__('resale.holder.unassigned')" />
                        @elseif ($subscription->is_own_holding)
                            {{ __('resale.holder.own') }}
                        @elseif ($subscription->foreignCustomer !== null)
                            {{ $subscription->foreignCustomer->name }}
                            <span class="block text-xs text-muted">{{ __('resale.holder.via', ['partner' => $subscription->foreignCustomer->customer?->name]) }}</span>
                        @else
                            <a href="{{ route('customers.show', $subscription->customer) }}" class="link link-hover">{{ $subscription->customer?->name }}</a>
                        @endif
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('resale.field.billed_to')">
                        @if ($billedTo !== null)
                            <a href="{{ route('customers.show', $billedTo) }}" class="link link-hover">{{ $billedTo->name }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('resale.field.status')"><x-status-badge size="xs" :tone="$subscription->status->tone()" :label="$subscription->status->label()" /></x-detail-grid.row>
                    @if ($subscription->contract !== null)
                        <x-detail-grid.row :label="__('resale.contract.field')">
                            @if ($contractLink !== null)
                                <a href="{{ $contractLink }}" class="link link-hover">{{ $subscription->contract->number }} · {{ $subscription->contract->title }}</a>
                            @else
                                {{ $subscription->contract->number }} · {{ $subscription->contract->title }}
                            @endif
                        </x-detail-grid.row>
                    @endif
                    @if ($subscription->company_name)
                        <x-detail-grid.row :label="__('resale.field.company_name')">{{ $subscription->company_name }}</x-detail-grid.row>
                    @endif
                    @if ($subscription->successor !== null)
                        <x-detail-grid.row :label="__('resale.field.successor')"><a href="{{ route('finance.resale.show', $subscription->successor->sqid) }}" class="link link-hover">{{ $subscription->successor->label }} ({{ $subscription->successor->provider->label() }})</a></x-detail-grid.row>
                    @endif
                    @foreach ($subscription->predecessors as $predecessor)
                        <x-detail-grid.row :label="__('resale.field.predecessor')"><a href="{{ route('finance.resale.show', $predecessor->sqid) }}" class="link link-hover">{{ $predecessor->label }} ({{ $predecessor->provider->label() }})</a></x-detail-grid.row>
                    @endforeach
                    @if ($subscription->parent !== null)
                        <x-detail-grid.row :label="__('resale.transfer.from')"><a href="{{ route('finance.resale.show', $subscription->parent->sqid) }}" class="link link-hover">{{ $subscription->parent->holderLabel() }} · {{ $subscription->parent->identityLabel() }}</a></x-detail-grid.row>
                    @endif
                    @foreach ($subscription->assignments as $assignment)
                        <x-detail-grid.row :label="__('resale.transfer.section')">
                            <a href="{{ route('finance.resale.show', $assignment->sqid) }}" class="link link-hover">{{ $assignment->holderLabel() }}</a>
                            · ×{{ $assignment->quantity }} · {{ $assignment->starts_on->fdate() }}@if ($assignment->ends_on) – {{ $assignment->ends_on->fdate() }}@endif
                        </x-detail-grid.row>
                    @endforeach
                </x-detail-grid>
            </x-card>

            <x-card :title="__('resale.section.terms')">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('resale.field.quantity')" class="tabular-nums">
                        {{ $subscription->quantity }}
                        @if ($subscription->assignments->isNotEmpty())
                            <span class="block text-xs text-warning">{{ trans_choice('resale.transfer.assigned_hint', $assignedNow, ['count' => $assignedNow]) }}</span>
                        @endif
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('resale.field.starts_on')" class="tabular-nums">{{ $subscription->starts_on->fdate() }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('resale.field.ends_on')" class="tabular-nums">{{ $subscription->ends_on?->fdate() ?? __('resale.value.open_end') }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('resale.field.term_months')" class="tabular-nums">{{ $subscription->term_months }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('resale.field.interval')">{{ $subscription->interval->label() }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('resale.field.renewal')">{{ $subscription->renewal->label() }}</x-detail-grid.row>
                    @if ($subscription->external_id)
                        <x-detail-grid.row :label="__('resale.field.external_id')" class="font-mono text-xs">{{ $subscription->external_id }}</x-detail-grid.row>
                    @endif
                    @if ($subscription->external_order_id)
                        <x-detail-grid.row :label="__('resale.field.external_order_id')" class="font-mono text-xs">{{ $subscription->external_order_id }}</x-detail-grid.row>
                    @endif
                </x-detail-grid>
            </x-card>

            <x-card :title="__('resale.section.prices')">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('resale.field.article')">{{ $subscription->productLabel() ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('resale.field.purchase_unit_price')" class="tabular-nums">{{ $subscription->purchase_unit_price?->withScale(2)->format() ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('resale.field.sale_unit_price')" class="tabular-nums">{{ $subscription->sale_unit_price?->withScale(2)->format() ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('resale.field.expected_sale')" class="tabular-nums">{{ $subscription->expectedSalePerPeriod()?->withScale(2)->format() ?? '—' }}</x-detail-grid.row>
                    @if ($subscription->purchase_unit_price !== null && $subscription->sale_unit_price !== null)
                        <x-detail-grid.row :label="__('resale.field.margin')" class="tabular-nums">{{ $subscription->sale_unit_price->minus($subscription->purchase_unit_price)->withScale(2)->format() }}</x-detail-grid.row>
                    @endif
                </x-detail-grid>
                @if ($subscription->notes)
                    <p class="mt-3 text-sm whitespace-pre-line">{{ $subscription->notes }}</p>
                @endif
            </x-card>
        </div>

        <x-card :title="__('resale.section.periods')" padding="p-0">
            <div class="flex items-center justify-between gap-2 border-b border-base-300 px-4 py-2 text-sm">
                <span class="text-base-content/70">{{ trans_choice('resale.periods.count', $subscription->periods->count(), ['count' => $subscription->periods->count()]) }}</span>
                @if ($subscription->is_own_holding)
                    <x-status-badge>{{ __('resale.holder.own') }}</x-status-badge>
                @elseif ($openCount > 0)
                    <x-status-badge tone="error">{{ trans_choice('resale.periods.open_count', $openCount, ['count' => $openCount]) }}</x-status-badge>
                @else
                    <x-status-badge tone="success">{{ __('resale.periods.all_decided') }}</x-status-badge>
                @endif
            </div>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('resale.field.period') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.field.quantity') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.field.expected_sale') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.link.covered') }}</x-table.th>
                        <x-table.th>{{ __('resale.link.links') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.status') }}</x-table.th>
                        <x-table.th class="text-right"></x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($subscription->periods as $period)
                    @include('finance.resale._period_row', ['period' => $period, 'subscription' => $subscription, 'showSubscription' => false, 'canManage' => $canManage, 'today' => $today])
                @empty
                    <x-table.empty :colspan="7" :title="__('resale.empty.periods')" compact />
                @endforelse
            </x-table>
        </x-card>

        {{-- Rechnungen des Rechnungsempfängers (Belegspiegel) mit Schnellzuordnung je Position. --}}
        @if (! $subscription->is_own_holding && $subscription->hasHolder())
            <x-card :title="__('resale.invoices.title')" padding="p-0" class="mt-4">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-4 py-2 text-sm">
                    <span class="text-base-content/70">
                        {{ __('resale.invoices.subtitle', ['customer' => $billedTo?->name ?? '—']) }}
                        @if ($invoices['hidden'] > 0)
                            · {{ trans_choice('resale.invoices.hidden', $invoices['hidden'], ['count' => $invoices['hidden']]) }}
                        @endif
                    </span>
                    <span class="flex items-center gap-2">
                        @if ($invoices['pending'] > 0)
                            <x-status-badge tone="warning" title="{{ __('resale.link_ui.pending_hint') }}">{{ trans_choice('resale.invoices.pending', $invoices['pending'], ['count' => $invoices['pending']]) }}</x-status-badge>
                        @endif
                        @if ($billedTo !== null)
                            <x-icon-btn icon="compare_arrows" size="xs" tone="ghost" :href="route('finance.resale.reconcile.show', $billedTo)" show-label>{{ __('resale.reconcile.title') }}</x-icon-btn>
                        @endif
                    </span>
                </div>
                @if (! $invoices['has_source'])
                    <div class="px-4 py-3 text-sm text-warning">{{ __('resale.mirror.no_source') }}</div>
                @else
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('resale.invoices.voucher') }}</x-table.th>
                                <x-table.th>{{ __('resale.invoices.line') }}</x-table.th>
                                <x-table.th class="text-right">{{ __('resale.field.quantity') }}</x-table.th>
                                <x-table.th class="text-right">{{ __('resale.invoices.unit_net') }}</x-table.th>
                                <x-table.th>{{ __('resale.invoices.linked') }}</x-table.th>
                                <x-table.th class="text-right">{{ __('resale.invoices.assign') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @forelse ($invoices['vouchers'] as $entry)
                            @php $voucher = $entry['voucher']; @endphp
                            @foreach ($entry['licence'] as $row)
                                @php $line = $row['line']; @endphp
                                <tr @class(['opacity-60' => $row['remaining'] <= 0.001])>
                                    @if ($loop->first)
                                        <td class="whitespace-nowrap align-top" rowspan="{{ $entry['rows'] }}">
                                            <span class="font-mono text-xs">{{ $voucher->voucherNumber }}</span>
                                            <span class="block text-xs text-muted tabular-nums">{{ $voucher->voucherDate?->fdate() }}</span>
                                            @if ($voucher->voucherTextHint !== null)
                                                <x-status-badge tone="info" outline class="mt-1" title="{{ $voucher->voucherText }}">{{ $voucher->voucherTextHint }}</x-status-badge>
                                            @elseif ($voucher->voucherText)
                                                <span class="block text-xs text-muted max-w-xs truncate" title="{{ $voucher->voucherText }}">{{ \Illuminate\Support\Str::limit($voucher->voucherText, 60) }}</span>
                                            @endif
                                            <span class="flex gap-1 mt-1">
                                                @if ($voucher->previewUrl !== null)
                                                    <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$voucher->previewUrl" :title="__('resale.invoices.preview')" />
                                                @endif
                                                @if ($entry['permalink'] !== null)
                                                    <x-external-link :url="$entry['permalink']" plain class="btn btn-ghost btn-xs" :title="__('resale.mirror.open_source', ['source' => __('resale.mirror.source_' . $voucher->sourceKey)])"><x-icon name="open_in_new" size="1rem" /></x-external-link>
                                                @endif
                                            </span>
                                        </td>
                                    @endif
                                    <td class="text-sm">
                                        {{ $line->name }}
                                        @if ($line->description)
                                            <span class="block text-xs text-muted max-w-sm truncate" title="{{ $line->description }}">{{ \Illuminate\Support\Str::limit($line->description, 80) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right tabular-nums whitespace-nowrap"><x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }}</td>
                                    <td class="text-right tabular-nums whitespace-nowrap">{{ $line->unitNet->withScale(2)->format() }}</td>
                                    <td class="text-xs">
                                        <span class="block text-muted tabular-nums">= <x-resale.licence-months :value="$row['months']" :per-licence="$row['per_licence']" /></span>
                                        @if ($row['linked'] !== null)
                                            <x-resale.licence-months class="text-success" :value="$row['linked']['months']" :per-licence="$row['per_licence']" />
                                            <span class="block text-muted">{{ implode(' · ', array_unique($row['linked']['periods'])) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                        @if ($row['remaining'] > 0.001 && $row['linked'] !== null)
                                            <span class="block text-muted">{{ __('resale.invoices.remaining', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label($row['remaining'], $row['per_licence'])]) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($canManage && $row['remaining'] > 0.001 && $openPeriods->isNotEmpty())
                                            <form method="POST" action="{{ route('finance.resale.links.quick', $subscription->sqid) }}" class="flex items-center justify-end gap-1">
                                                @csrf
                                                <input type="hidden" name="line_id" value="{{ $line->key }}">
                                                <select name="period_id" class="select select-xs select-bordered w-44" aria-label="{{ __('resale.field.period') }}">
                                                    @foreach ($openPeriods as $period)
                                                        <option value="{{ $period->sqid }}" @selected($period->starts_on->lessThanOrEqualTo($voucher->voucherDate ?? $today) && $period->ends_on->greaterThanOrEqualTo($voucher->voucherDate ?? $today))>{{ $period->label() }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="hidden" name="per_licence" value="{{ number_format($row['per_licence'], 2, '.', '') }}">
                                                <input type="number" name="licences" step="0.01" min="0.01" value="{{ number_format($row['default_licences'], 2, '.', '') }}"
                                                       class="input input-xs input-bordered w-16 text-right" aria-label="{{ __('resale.link.licences_field') }}" title="{{ __('resale.link_ui.licences_times', ['months' => \App\View\Components\Resale\LicenceMonths::compact($row['per_licence'])]) }}">
                                                <x-icon-btn icon="add_link" size="xs" tone="primary" type="submit" :title="__('resale.link.action.link')" />
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            @if ($entry['other'] !== [])
                                <tr class="bg-base-200/40">
                                    <td colspan="5" class="text-xs">
                                        <details>
                                            <summary class="cursor-pointer select-none text-muted">{{ trans_choice('resale.invoices.other_lines', count($entry['other']), ['count' => count($entry['other'])]) }}</summary>
                                            <ul class="mt-1 space-y-0.5">
                                                @foreach ($entry['other'] as $other)
                                                    <li class="flex justify-between gap-3">
                                                        <span class="truncate" title="{{ $other->description }}">{{ $other->name }}</span>
                                                        <span class="whitespace-nowrap tabular-nums text-muted"><x-resale.licence-months :value="$other->quantity" />{{ $other->unitName ? ' ' . $other->unitName : '' }} × {{ $other->unitNet->withScale(2)->format() }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <x-table.empty :colspan="6" :title="$invoices['pending'] > 0 ? __('resale.invoices.empty_pending') : __('resale.invoices.empty')" compact />
                        @endforelse
                    </x-table>
                @endif
            </x-card>
        @endif
    </x-index-page>
@endsection
