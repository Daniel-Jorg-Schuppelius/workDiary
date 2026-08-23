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
            @if (! $run->isExported())
                <x-action-form :action="route('finance.payment-runs.cancel', $run)" :confirm="__('sepa.confirm_cancel')">
                    <x-icon-btn icon="cancel" tone="ghost" size="sm" type="submit"
                                show-label>{{ __('sepa.action.cancel') }}</x-icon-btn>
                </x-action-form>
            @endif
        </x-slot:actions>

        <x-card>
            <dl class="grid gap-3 text-sm sm:grid-cols-4">
                <div><dt class="text-base-content/60">{{ __('sepa.column.kind') }}</dt><dd>{{ $run->kind->label() }}</dd></div>
                <div><dt class="text-base-content/60">{{ __('sepa.column.account') }}</dt><dd>{{ $run->bankAccount?->label ?? '—' }}</dd></div>
                <div><dt class="text-base-content/60">{{ __('sepa.column.execution_date') }}</dt><dd>{{ optional($run->execution_date)->fdate() ?? '—' }}</dd></div>
                <div><dt class="text-base-content/60">{{ __('sepa.column.total') }}</dt><dd class="font-medium tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $run->total, 2, withThousandsSeparator: true) }}</dd></div>
                @if ($run->released_at)
                    <div><dt class="text-base-content/60">{{ __('sepa.released_by') }}</dt><dd>{{ $run->releasedBy?->name ?? '—' }} · {{ $run->released_at->fdatetime() }}</dd></div>
                @endif
                @if ($run->file_sha256)
                    {{-- Der Hash ist der Beleg, dass ein zweiter Download dieselbe
                         Datei liefert und nicht eine neue Zahlung. --}}
                    <div class="sm:col-span-3"><dt class="text-base-content/60">{{ __('sepa.file_hash') }}</dt><dd class="font-mono text-xs break-all">{{ $run->file_sha256 }}</dd></div>
                @endif
            </dl>
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
