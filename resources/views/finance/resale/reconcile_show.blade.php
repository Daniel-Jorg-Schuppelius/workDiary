{{--
  Created on   : Mon Sep 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : reconcile_show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abgleich eines Rechnungsempfängers (Feature 152): Bilanz je Produkt in
  Lizenzmonaten, offene Perioden aller Abos mit den Positionen desselben
  Produkts (frei oder schon vergeben) und alle Lizenzpositionen mit
  Verbrauch. Zuordnung über Abo-Grenzen hinweg, Verzicht und Rechnungs-
  entwurf direkt hier. Perioden-Reihenfolge und Ziele je Produkt
  ($allPeriods, $targetsByProduct) kommen aus dem Controller.
--}}
@extends('layouts.app')
@section('title', __('resale.reconcile.show_title', ['customer' => $customer->name]))
@section('nav-title', __('resale.title.menu'))

@php
    $canManage = auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false;
    $compact = \App\View\Components\Resale\LicenceMonths::class;
@endphp

@section('content')
    <x-index-page :title="__('resale.reconcile.show_title', ['customer' => $customer->name])" :subtitle="__('resale.reconcile.show_subtitle')">
        <x-slot:actions>
            @if ($canManage)
                <form method="POST" action="{{ route('finance.resale.periods.propose') }}">
                    @csrf
                    <x-icon-btn icon="auto_awesome" tone="ghost" size="sm" type="submit" show-label>{{ __('resale.link.action.propose') }}</x-icon-btn>
                </form>
            @endif
            @can(\App\Enums\User\Permission::ResellingInvoice->value)
                <x-icon-btn icon="receipt_long" tone="ghost" size="sm" data-entry-modal-trigger :href="route('finance.resale.periods.draft.create')" show-label>{{ __('resale.draft.action') }}</x-icon-btn>
            @endcan
            <x-icon-btn icon="person" tone="ghost" size="sm" :href="route('customers.show', $customer)" show-label>{{ __('resale.reconcile.action.customer') }}</x-icon-btn>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('finance.resale.reconcile.index')" show-label>{{ __('resale.action.back') }}</x-icon-btn>
        </x-slot:actions>

        {{-- Zuordnung und Halterwechsel senden ohne Dialog: Feldfehler landen hier. --}}
        <x-validation-errors class="mb-4" />

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
            <x-kpi-tile :label="__('resale.reconcile.kpi.open')" :value="$open" :tone="$open > 0 ? 'error' : 'success'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.partial')" :value="$partial" :tone="$partial > 0 ? 'warning' : 'neutral'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.proposed')" :value="$proposed" :tone="$proposed > 0 ? 'info' : 'neutral'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.free')" :value="$compact::compact($free)" format="raw" :tone="$free > 0.001 ? 'warning' : 'neutral'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.missing')" :value="$compact::compact($missing)" format="raw" :tone="$missing > 0.001 ? 'error' : 'neutral'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.surplus')" :value="$compact::compact($surplus)" format="raw" :tone="$surplus > 0.001 ? 'info' : 'neutral'" />
        </div>

        @if (! $has_source)
            <div class="alert alert-warning mb-4 text-sm">{{ __('resale.mirror.no_source') }}</div>
        @elseif ($pending > 0)
            <div class="alert alert-info mb-4 text-sm" title="{{ __('resale.link_ui.pending_hint') }}">{{ trans_choice('resale.invoices.pending', $pending, ['count' => $pending]) }}</div>
        @endif

        @if ($inbox !== [])
            {{-- Endkunden im Rechnungstext, deren Abos noch ohne Halter sind. --}}
            <div class="alert alert-warning mb-4 text-sm">
                <div>
                    <span class="font-medium">{{ __('resale.reconcile.inbox.title') }}</span>
                    <span class="block text-xs">{{ __('resale.reconcile.inbox.hint', ['customer' => $customer->name]) }}</span>
                    <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        @foreach ($inbox as $hint)
                            <li>
                                <span class="font-medium">{{ $hint['company'] }}</span>
                                @foreach ($hint['subscriptions'] as $unassigned)
                                    · <a href="{{ route('finance.resale.show', $unassigned->sqid) }}" class="link link-hover">{{ $unassigned->label }} × {{ $unassigned->quantity }}</a>
                                @endforeach
                                · {{ trans_choice('resale.reconcile.inbox.mentions', $hint['mentions'], ['count' => $hint['mentions']]) }}
                            </li>
                        @endforeach
                    </ul>
                    <x-icon-btn icon="inbox" size="xs" tone="ghost" :href="route('finance.resale.inbox')" show-label class="mt-1">{{ __('resale.inbox.title') }}</x-icon-btn>
                </div>
            </div>
        @endif

        {{-- Bilanz je Produkt: Soll aus Perioden gegen Ist aus Positionen. --}}
        <x-card :title="__('resale.reconcile.products.title')" padding="p-0" class="mb-4">
            <p class="px-4 py-2 text-xs text-muted border-b border-base-300">{{ __('resale.reconcile.products.hint') }}</p>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('resale.field.article') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.reconcile.col.subscriptions') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.reconcile.col.periods') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.reconcile.col.required') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.reconcile.col.covered') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.reconcile.col.invoiced') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.reconcile.kpi.free') }}</x-table.th>
                        <x-table.th>{{ __('resale.reconcile.col.verdict') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($products as $product)
                    @php $openMonths = max(0.0, $product['required'] - $product['covered']); @endphp
                    <tr>
                        <td class="text-sm font-medium">{{ $product['label'] }}</td>
                        <td class="text-right tabular-nums">{{ $product['subscriptions'] }}</td>
                        <td class="text-right tabular-nums">{{ $product['periods'] }}</td>
                        <td class="text-right tabular-nums"><x-resale.licence-months :value="$product['required']" /></td>
                        <td class="text-right tabular-nums"><x-resale.licence-months :value="$product['covered']" /></td>
                        <td class="text-right tabular-nums"><x-resale.licence-months :value="$product['invoiced']" /></td>
                        <td class="text-right tabular-nums"><span @class(['text-warning font-medium' => $product['free'] > 0.001])><x-resale.licence-months :value="$product['free']" /></span></td>
                        <td class="text-sm">
                            @if ($product['missing'] > 0.001)
                                <x-status-badge size="xs" tone="error" :label="__('resale.reconcile.verdict.missing', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label($product['missing'], (float) $product['term'])])" />
                            @elseif ($openMonths > 0.001)
                                <x-status-badge size="xs" tone="warning" :label="__('resale.reconcile.verdict.unassigned', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label($openMonths, (float) $product['term'])])" />
                            @endif
                            @if ($product['surplus'] > 0.001)
                                <x-status-badge size="xs" tone="info" :label="__('resale.reconcile.verdict.surplus', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label($product['surplus'], (float) $product['term'])])" />
                            @endif
                            @if ($product['missing'] <= 0.001 && $openMonths <= 0.001 && $product['surplus'] <= 0.001)
                                <x-status-badge size="xs" tone="success" :label="__('resale.reconcile.verdict.balanced')" />
                            @endif
                            @if ($product['gap_since'] !== null)
                                <span class="block text-xs text-warning mt-0.5">{{ __('resale.reconcile.verdict.gap_since', ['date' => $product['gap_since']->fdate()]) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="8" :title="__('resale.reconcile.products.empty')" compact />
                @endforelse
            </x-table>
        </x-card>

        {{-- Offene Perioden mit den Positionen desselben Produkts. --}}
        <x-card :title="__('resale.reconcile.periods.title')" padding="p-0" class="mb-4">
            <p class="px-4 py-2 text-xs text-muted border-b border-base-300">{{ __('resale.reconcile.periods.hint') }}</p>
            @forelse ($periods as $row)
                @php
                    $period = $row['period'];
                    $subscription = $row['subscription'];
                @endphp
                <div class="border-b border-base-300 last:border-b-0 px-4 py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="text-sm">
                            <a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover font-medium">{{ $subscription->label }}</a>
                            <span class="text-muted">· {{ $subscription->holderLabel() }}</span>
                            <span class="block text-xs text-muted tabular-nums">
                                {{ $period->label() }} · {{ $subscription->provider->label() }} · {{ __('resale.field.quantity') }} {{ $period->quantity }}
                                @if ($subscription->status !== \App\Enums\Reselling\SubscriptionStatus::Active)
                                    · {{ $subscription->status->label() }}
                                @endif
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm tabular-nums whitespace-nowrap" title="{{ __('resale.reconcile.periods.need', ['licences' => $period->quantity, 'months' => $period->termMonths()]) }}">
                                <span @class(['text-error font-medium' => $row['covered'] <= 0.001, 'text-warning font-medium' => $row['covered'] > 0.001])>{{ __('resale.link.licences_of', ['covered' => $compact::compact($row['covered'] / max(1, $period->termMonths())), 'quantity' => $period->quantity, 'months' => $period->termMonths()]) }}</span>
                            </span>
                            <x-status-badge size="xs" :tone="$period->status->tone()" :label="$period->status->label()" />
                            @if ($canManage)
                                <x-icon-btn icon="block" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.periods.waive.create', $period->sqid)" :title="__('resale.link.action.waive')" />
                            @endif
                        </div>
                    </div>
                    @if ($row['candidates'] === [] && $row['taken'] === [] && $row['foreign'] === [] && $row['voided'] === [])
                        <p class="mt-2 text-xs text-error">{{ __('resale.reconcile.periods.none', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label($row['needed'], (float) $period->termMonths())]) }}</p>
                    @else
                        <ul class="mt-2 space-y-1">
                            @foreach (array_slice($row['foreign'], 0, 4) as $candidate)
                                @php
                                    $line = $candidate['row']['line'];
                                    $voucher = $line;
                                    $recipient = $candidate['row']['recipient'] ?? '—';
                                    $targetId = $candidate['row']['recipient_id'];
                                @endphp
                                <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <x-status-badge size="xs" tone="warning" :label="__('resale.reconcile.periods.foreign', ['recipient' => $recipient])" />
                                        <span class="font-mono text-xs">{{ $voucher->voucherNumber }}</span>
                                        <span class="text-xs text-muted tabular-nums">{{ $voucher->voucherDate?->fdate() }}@if ($voucher->servicePeriodLabel() !== null) · {{ __('resale.reconcile.service_period') }} {{ $voucher->servicePeriodLabel() }}@endif · {{ trans_choice('resale.reconcile.distance', $candidate['distance'], ['days' => $candidate['distance']]) }}</span>
                                        <span><x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }} × {{ $line->unitNet->withScale(2)->format() }}</span>
                                        <span class="text-xs text-muted tabular-nums">= <x-resale.licence-months :value="$candidate['row']['months']" :per-licence="$candidate['row']['per_licence']" /></span>
                                        @if ($line->previewUrl !== null)
                                            <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$line->previewUrl" :title="__('resale.invoices.preview')" />
                                        @endif
                                    </span>
                                    @if ($canManage && $targetId !== null)
                                        <span class="flex flex-wrap items-center gap-1">
                                            @if (! $subscription->isAssignment())
                                                {{-- Halterwechsel im Zeitverlauf: alle Lizenzen dieser Periode an den anderen Kunden abtreten. --}}
                                                <x-icon-btn icon="call_split" size="xs" tone="ghost" data-entry-modal-trigger
                                                            :href="route('finance.resale.transfer.create', ['subscription' => $subscription->sqid, 'customer' => \App\Support\Sqid::encode(\App\Models\Customer::class, $targetId), 'quantity' => (int) round($candidate['row']['licences']), 'starts_on' => $period->starts_on->toDateString(), 'ends_on' => $period->ends_on->toDateString()])"
                                                            show-label>{{ __('resale.transfer.action_period', ['customer' => $recipient]) }}</x-icon-btn>
                                            @endif
                                            <form method="POST" action="{{ route('finance.resale.reconcile.rehome', $customer) }}" data-confirm-dialog data-confirm-message="{{ __('resale.reconcile.confirm.rehome', ['subscription' => $subscription->label, 'customer' => $recipient]) }}" data-confirm-tone="error">
                                                @csrf
                                                <input type="hidden" name="period_id" value="{{ $period->sqid }}">
                                                <input type="hidden" name="target_id" value="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $targetId) }}">
                                                <x-icon-btn icon="swap_horiz" size="xs" tone="warning" type="submit" show-label>{{ __('resale.reconcile.action.rehome', ['customer' => $recipient]) }}</x-icon-btn>
                                            </form>
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                            @foreach (array_slice($row['candidates'], 0, 6) as $candidate)
                                @php
                                    $line = $candidate['row']['line'];
                                    $voucher = $line;
                                @endphp
                                <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-xs">{{ $voucher->voucherNumber }}</span>
                                        <span class="text-xs text-muted tabular-nums">{{ $voucher->voucherDate?->fdate() }}@if ($voucher->servicePeriodLabel() !== null) · {{ __('resale.reconcile.service_period') }} {{ $voucher->servicePeriodLabel() }}@endif · {{ trans_choice('resale.reconcile.distance', $candidate['distance'], ['days' => $candidate['distance']]) }}</span>
                                        <span><x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }} × {{ $line->unitNet->withScale(2)->format() }}</span>
                                        <span class="text-xs text-muted tabular-nums">= <x-resale.licence-months :value="$candidate['row']['months']" :per-licence="$candidate['row']['per_licence']" /></span>
                                        <span class="text-success text-xs">{{ __('resale.invoices.remaining', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label($candidate['row']['free'], $candidate['row']['per_licence'])]) }}</span>
                                        @if ($voucher->voucherTextHint !== null)
                                            <span class="badge badge-info badge-outline badge-xs" title="{{ $voucher->voucherText }}">{{ $voucher->voucherTextHint }}</span>
                                        @endif
                                        @if ($line->previewUrl !== null)
                                            <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$line->previewUrl" :title="__('resale.invoices.preview')" />
                                        @endif
                                    </span>
                                    @if ($canManage)
                                        <form method="POST" action="{{ route('finance.resale.reconcile.assign', $customer) }}" class="flex items-center gap-1">
                                            @csrf
                                            <input type="hidden" name="period_id" value="{{ $period->sqid }}">
                                            <input type="hidden" name="line_id" value="{{ $line->key }}">
                                            <input type="hidden" name="per_licence" value="{{ number_format($candidate['row']['per_licence'], 2, '.', '') }}">
                                            <input type="number" name="licences" step="0.01" min="0.01" value="{{ number_format(min($row['needed'], $candidate['row']['free']) / max(0.01, $candidate['row']['per_licence']), 2, '.', '') }}"
                                                   class="input input-xs input-bordered w-16 text-right" aria-label="{{ __('resale.link.licences_field') }}" title="{{ __('resale.link_ui.licences_times', ['months' => $compact::compact($candidate['row']['per_licence'])]) }}">
                                            <x-icon-btn icon="add_link" size="xs" tone="primary" type="submit" show-label>{{ __('resale.link.action.link') }}</x-icon-btn>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                            @foreach (array_slice($row['taken'], 0, 4) as $candidate)
                                @php $line = $candidate['row']['line']; @endphp
                                <li class="flex flex-wrap items-center gap-2 text-xs text-muted">
                                    <span class="font-mono">{{ $line->voucherNumber }}</span>
                                    <span class="tabular-nums">{{ $line->voucherDate?->fdate() }}</span>
                                    <span><x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }}</span>
                                    <span>{{ __('resale.reconcile.periods.taken', ['periods' => implode(' · ', array_unique($candidate['row']['periods']))]) }}</span>
                                </li>
                            @endforeach
                            @foreach (array_slice($row['voided'], 0, 3) as $candidate)
                                @php $line = $candidate['row']['line']; @endphp
                                <li class="flex flex-wrap items-center gap-2 text-xs text-muted">
                                    <x-status-badge size="xs" tone="error" :label="__('resale.reconcile.periods.voided')" />
                                    <span class="font-mono">{{ $line->voucherNumber }}</span>
                                    <span class="tabular-nums">{{ $line->voucherDate?->fdate() }}</span>
                                    <span><x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }} · <x-resale.licence-months :value="$candidate['row']['months']" :per-licence="$candidate['row']['per_licence']" /></span>
                                    @if ($line->previewUrl !== null)
                                        <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$line->previewUrl" :title="__('resale.invoices.preview')" />
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if ($row['candidates'] === [] && $row['foreign'] === [])
                            <p class="mt-1 text-xs text-error">{{ __('resale.reconcile.periods.all_taken', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label($row['needed'], (float) $period->termMonths())]) }}</p>
                        @endif
                    @endif
                </div>
            @empty
                <div class="px-4 py-6 text-center text-sm text-muted">{{ __('resale.reconcile.periods.empty') }}</div>
            @endforelse
        </x-card>

        {{-- Alle Abrechnungsperioden des Empfängers (auch gedeckte) — „wie viele gibt es, was ist gedeckt". --}}
        <details class="collapse collapse-arrow border border-base-300 bg-base-100 mb-4">
            <summary class="collapse-title text-sm font-medium">{{ trans_choice('resale.reconcile.all_periods', count($allPeriods), ['count' => count($allPeriods)]) }}</summary>
            <div class="collapse-content p-0">
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('resale.field.label') }}</x-table.th>
                            <x-table.th>{{ __('resale.field.holder') }}</x-table.th>
                            <x-table.th>{{ __('resale.field.period') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('resale.field.quantity') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('resale.field.expected_sale') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('resale.link.covered') }}</x-table.th>
                            <x-table.th>{{ __('resale.link.links') }}</x-table.th>
                            <x-table.th>{{ __('resale.field.status') }}</x-table.th>
                            <x-table.th class="text-right"></x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($allPeriods as $row)
                        @include('finance.resale._period_row', ['period' => $row['period'], 'subscription' => $row['subscription'], 'showSubscription' => true, 'canManage' => $canManage, 'today' => $today])
                    @endforeach
                </x-table>
            </div>
        </details>

        {{-- Alle Lizenzpositionen des Empfängers mit Verbrauch. --}}
        <x-card :title="__('resale.reconcile.lines.title')" padding="p-0">
            <p class="px-4 py-2 text-xs text-muted border-b border-base-300">{{ __('resale.reconcile.lines.hint') }}</p>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('resale.invoices.voucher') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.article') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.field.quantity') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.reconcile.col.licence_months') }}</x-table.th>
                        <x-table.th>{{ __('resale.invoices.linked') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.invoices.assign') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($lines as $lineRow)
                    @php
                        $line = $lineRow['line'];
                        $voucher = $line;
                        $sameProduct = $targetsByProduct[$lineRow['product']] ?? [];
                    @endphp
                    <tr @class(['opacity-60' => $lineRow['free'] <= 0.001])>
                        <td class="whitespace-nowrap">
                            <span class="font-mono text-xs">{{ $voucher->voucherNumber }}</span>
                            <span class="text-xs text-muted tabular-nums">{{ $voucher->voucherDate?->fdate() }}</span>
                            @if ($voucher->servicePeriodLabel() !== null)
                                <span class="block text-xs text-muted tabular-nums">{{ __('resale.reconcile.service_period') }} {{ $voucher->servicePeriodLabel() }}</span>
                            @endif
                            @if ($voucher->voucherTextHint !== null)
                                <span class="badge badge-info badge-outline badge-xs ml-1" title="{{ $voucher->voucherText }}">{{ $voucher->voucherTextHint }}</span>
                            @endif
                            @if ($line->previewUrl !== null)
                                <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$line->previewUrl" :title="__('resale.invoices.preview')" />
                            @endif
                        </td>
                        <td class="text-sm">{{ $line->label() }}</td>
                        <td class="text-right tabular-nums whitespace-nowrap"><x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }}</td>
                        <td class="text-right tabular-nums whitespace-nowrap"><x-resale.licence-months :value="$lineRow['months']" :per-licence="$lineRow['per_licence']" /></td>
                        <td class="text-xs">
                            @if ($lineRow['linked'] > 0.001)
                                <span class="text-success">{{ \App\Services\Reselling\Register\LicenseMonths::label($lineRow['linked'], $lineRow['per_licence']) }}</span>
                                <span class="block text-muted">{{ implode(' · ', array_unique($lineRow['periods'])) }}</span>
                            @endif
                            @if ($lineRow['free'] > 0.001)
                                <span class="block text-warning">{{ __('resale.invoices.remaining', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label($lineRow['free'], $lineRow['per_licence'])]) }}</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if ($canManage && $lineRow['gap'])
                                {{-- Keine Periode dieses Produkts um das Bezugsdatum: das Abo fehlt im Register (z. B. nicht im Anbieter-Export). --}}
                                <x-icon-btn icon="add" size="xs" tone="warning" data-entry-modal-trigger
                                            :href="route('finance.resale.create', ['customer' => $customer->sqid, 'line' => $line->key])"
                                            show-label>{{ __('resale.reconcile.action.create_from_line') }}</x-icon-btn>
                            @endif
                            @if ($canManage && $lineRow['free'] > 0.001 && $periods !== [])
                                <form method="POST" action="{{ route('finance.resale.reconcile.assign', $customer) }}" class="flex items-center justify-end gap-1 mt-1">
                                    @csrf
                                    <input type="hidden" name="line_id" value="{{ $line->key }}">
                                    <select name="period_id" class="select select-xs select-bordered w-64" aria-label="{{ __('resale.field.period') }}">
                                        @foreach ($sameProduct !== [] ? $sameProduct : $periods as $target)
                                            <option value="{{ $target['period']->sqid }}" @selected($loop->first)>{{ $target['subscription']->label }} · {{ $target['period']->label() }} ({{ \App\Services\Reselling\Register\LicenseMonths::label($target['needed'], (float) $target['period']->termMonths()) }})</option>
                                        @endforeach
                                        @if ($sameProduct !== [] && count($sameProduct) < count($periods))
                                            <optgroup label="{{ __('resale.reconcile.lines.other_products') }}">
                                                @foreach ($periods as $target)
                                                    @if ($target['product'] !== $lineRow['product'])
                                                        <option value="{{ $target['period']->sqid }}">{{ $target['subscription']->label }} · {{ $target['period']->label() }} ({{ \App\Services\Reselling\Register\LicenseMonths::label($target['needed'], (float) $target['period']->termMonths()) }})</option>
                                                    @endif
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                    <input type="hidden" name="per_licence" value="{{ number_format($lineRow['per_licence'], 2, '.', '') }}">
                                    <input type="number" name="licences" step="0.01" min="0.01" value="{{ number_format(min($lineRow['free'], ($sameProduct[0] ?? $periods[0])['needed']) / max(0.01, $lineRow['per_licence']), 2, '.', '') }}"
                                           class="input input-xs input-bordered w-16 text-right" aria-label="{{ __('resale.link.licences_field') }}" title="{{ __('resale.link_ui.licences_times', ['months' => $compact::compact($lineRow['per_licence'])]) }}">
                                    <x-icon-btn icon="add_link" size="xs" tone="primary" type="submit" :title="__('resale.link.action.link')" />
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="6" :title="$pending > 0 ? __('resale.invoices.empty_pending') : __('resale.invoices.empty')" compact />
                @endforelse
            </x-table>
        </x-card>

        {{-- Gutschriften des Empfängers (Review 2026-09-10, A3): mindern die Deckung; Zuordnung nur von Hand,
             die Menge wird positiv eingegeben (gutgeschriebene Lizenzen) und vom PeriodLinker negativ verbucht. --}}
        @if ($credit_notes !== [])
            <x-card :title="__('resale.credit_notes.title')" padding="p-0" class="mt-4">
                <p class="px-4 py-2 text-xs text-muted border-b border-base-300">{{ __('resale.credit_notes.hint') }}</p>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('resale.invoices.voucher') }}</x-table.th>
                            <x-table.th>{{ __('resale.field.article') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('resale.reconcile.col.licence_months') }}</x-table.th>
                            <x-table.th>{{ __('resale.credit_notes.col_linked') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('resale.invoices.assign') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($credit_notes as $creditRow)
                        @php
                            $line = $creditRow['line'];
                            $voucher = $line;
                            $creditFree = max(0.0, $creditRow['months'] - $creditRow['linked']);
                        @endphp
                        <tr @class(['opacity-60' => $creditFree <= 0.001])>
                            <td class="whitespace-nowrap">
                                <span class="font-mono text-xs">{{ $voucher->voucherNumber }}</span>
                                <span class="text-xs text-muted tabular-nums">{{ $voucher->voucherDate?->fdate() }} · {{ __('resale.link_ui.position', ['position' => $line->position]) }}</span>
                                @if ($voucher->servicePeriodLabel() !== null)
                                    <span class="block text-xs text-muted tabular-nums">{{ __('resale.reconcile.service_period') }} {{ $voucher->servicePeriodLabel() }}</span>
                                @endif
                                @if ($line->previewUrl !== null)
                                    <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$line->previewUrl" :title="__('resale.invoices.preview')" />
                                @endif
                            </td>
                            <td class="text-sm">{{ $line->label() }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap text-error">−<x-resale.licence-months :value="$creditRow['months']" :per-licence="$creditRow['per_licence']" /></td>
                            <td class="text-xs">
                                @if ($creditRow['linked'] > 0.001)
                                    <span class="text-success">{{ __('resale.credit_notes.linked_with', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label($creditRow['linked'], $creditRow['per_licence']), 'periods' => implode(' · ', array_unique($creditRow['periods']))]) }}</span>
                                @else
                                    <span class="text-warning">{{ __('resale.credit_notes.unlinked') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($canManage && $creditFree > 0.001 && $allPeriods !== [])
                                    <form method="POST" action="{{ route('finance.resale.reconcile.assign', $customer) }}" class="flex items-center justify-end gap-1" title="{{ __('resale.credit_notes.sign_hint') }}">
                                        @csrf
                                        <input type="hidden" name="line_id" value="{{ $line->key }}">
                                        <select name="period_id" class="select select-xs select-bordered w-64" aria-label="{{ __('resale.field.period') }}">
                                            @foreach ($allPeriods as $target)
                                                <option value="{{ $target['period']->sqid }}">{{ $target['subscription']->label }} · {{ $target['period']->label() }} ({{ $target['period']->status->label() }})</option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="per_licence" value="{{ number_format($creditRow['per_licence'], 2, '.', '') }}">
                                        <input type="number" name="licences" step="0.01" min="0.01" value="{{ number_format($creditFree / max(0.01, $creditRow['per_licence']), 2, '.', '') }}"
                                               class="input input-xs input-bordered w-16 text-right" aria-label="{{ __('resale.credit_notes.licences_field') }}" title="{{ __('resale.link_ui.licences_times', ['months' => $compact::compact($creditRow['per_licence'])]) }}">
                                        <x-icon-btn icon="remove_circle" size="xs" tone="error" type="submit" :title="__('resale.credit_notes.action_link')" />
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif
    </x-index-page>
@endsection
