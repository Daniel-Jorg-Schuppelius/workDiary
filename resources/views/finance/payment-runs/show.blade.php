{{--
  Created on   : Thu Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zahllauf-Detail (Feature 120, MVP-609): Positionen, Freigabe, Export.
--}}

@extends('layouts.app')

@section('title', __('sepa.title'))
@section('nav-title', __('sepa.title'))

@section('content')
    <x-index-page :subtitle="$run->label ?: ($run->message_id ?? __('sepa.status.draft'))">
        <x-slot:actions>
            @if ($run->isDraft() && $canRelease)
                <x-action-form :action="route('finance.payment-runs.release', $run)"
                               :confirm="__('sepa.confirm_release', ['count' => $run->items->count()])">
                    <x-icon-btn icon="task_alt" tone="primary" size="sm" type="submit"
                                show-label>{{ __('sepa.action.release') }}</x-icon-btn>
                </x-action-form>
            @endif
            @if (($run->isReleased() || $run->isExported()) && $formatsAvailable)
                <x-action-form :action="route('finance.payment-runs.export', $run)">
                    <x-icon-btn icon="download" tone="primary" size="sm" type="submit"
                                show-label>{{ __('sepa.action.export') }}</x-icon-btn>
                </x-action-form>
            @endif
            @if ($ebicsUnclear && $canRelease)
                <x-action-form :action="route('finance.payment-runs.ebics.not-submitted', $run)" :confirm="__('ebics.confirm.not_submitted')">
                    <x-icon-btn icon="report" tone="warning" size="sm" type="submit"
                                show-label>{{ __('ebics.action.confirm_not_submitted') }}</x-icon-btn>
                </x-action-form>
            @endif
            @if ($ebicsAvailable && $canRelease && $formatsAvailable && ($run->isReleased() || $run->isExported()) && $ebicsSubmission === null && $ebicsUnclear === null)
                <x-action-form :action="route('finance.payment-runs.ebics', $run)" :confirm="__('ebics.confirm.submit')">
                    <x-icon-btn icon="send" tone="primary" size="sm" type="submit"
                                show-label>{{ __('ebics.action.submit') }}</x-icon-btn>
                </x-action-form>
            @endif
            @if (! $run->isExported())
                <x-action-form :action="route('finance.payment-runs.cancel', $run)" :confirm="__('sepa.confirm_cancel')">
                    <x-icon-btn placement="danger" icon="cancel" tone="ghost" size="sm" type="submit"
                                show-label>{{ __('sepa.action.cancel') }}</x-icon-btn>
                </x-action-form>
            @endif
        </x-slot:actions>

        <x-card>
            <x-detail-grid layout="cells" :cols="4">
                <x-detail-grid.row :label="__('sepa.column.kind')">{{ $run->kind->label() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('sepa.column.account')">{{ $run->bankAccount?->label ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('sepa.column.execution_date')">{{ optional($run->execution_date)->fdate() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('sepa.column.total')" class="font-medium tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $run->total, 2, withThousandsSeparator: true) }}</x-detail-grid.row>
                @if ($ebicsUnclear)
                    <x-detail-grid.row label="EBICS" class="text-warning">{{ __('ebics.run.unclear', ['date' => $ebicsUnclear->occurredAt()?->fdatetime()]) }}</x-detail-grid.row>
                @endif
                @if ($ebicsSubmission)
                    <x-detail-grid.row label="EBICS">{{ __('ebics.run.submitted', ['date' => $ebicsSubmission->occurredAt()?->fdatetime(), 'order' => $ebicsSubmission->payloadData()['order_id'] ?? '—']) }}</x-detail-grid.row>
                @endif
                @if ($run->released_at)
                    <x-detail-grid.row :label="__('sepa.released_by')">{{ $run->releasedBy?->name ?? '—' }} · {{ $run->released_at->fdatetime() }}</x-detail-grid.row>
                @endif
                @if ($run->file_sha256)
                    {{-- Der Hash ist der Beleg, dass ein zweiter Download dieselbe
                         Datei liefert und nicht eine neue Zahlung. --}}
                    <x-detail-grid.row :label="__('sepa.file_hash')" full class="font-mono text-xs break-all">{{ $run->file_sha256 }}</x-detail-grid.row>
                @endif
            </x-detail-grid>
        </x-card>

        <x-table :pin-rows="true" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('sepa.column.creditor') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('sepa.column.reference') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('sepa.column.gross') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('sepa.column.amount') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('sepa.column.deduction') }}</x-table.th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($run->items as $item)
                <tr class="hover">
                    <td class="font-medium">{{ $item->party_name }}</td>
                    <td class="text-xs">{{ $item->reference }}</td>
                    <td class="text-right tabular-nums">{{ $item->gross_amount === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->gross_amount, 2, withThousandsSeparator: true) }}</td>
                    <td class="text-right tabular-nums font-medium">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->amount, 2, withThousandsSeparator: true) }}</td>
                    <td class="text-xs">
                        @if ($item->discount_percent !== null)
                            <x-status-badge tone="success" outline>{{ __('sepa.discount_used', ['percent' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->discount_percent, 2)]) }}</x-status-badge>
                        @endif
                        {{ $item->deduction_reason }}
                    </td>
                    <td class="text-right">
                        @if ($run->isDraft())
                            <div class="flex justify-end gap-1">
                                <x-icon-btn icon="edit" size="xs" tone="ghost"
                                            data-entry-modal-trigger
                                            :href="route('finance.payment-runs.items.adjust-form', [$run, $item])"
                                            :label="__('sepa.action.adjust')" />
                                <x-action-form :action="route('finance.payment-runs.items.remove', [$run, $item])">
                                    <x-icon-btn icon="remove_circle" size="xs" tone="ghost" type="submit"
                                                :label="__('sepa.action.remove_item')" />
                                </x-action-form>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6" icon="account_balance" :title="__('sepa.no_items')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
