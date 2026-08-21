{{--
  Created on   : Thu Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : proposals.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zahlungsvorschlag (Feature 120, MVP-609): Vorschlag heisst Vorschlag — jede
  Position ist abwählbar, bevor ein Lauf entsteht.
--}}

@extends('layouts.app')

@section('title', __('sepa.proposal.title'))
@section('nav-title', __('sepa.proposal.title'))

@section('content')
    <x-index-page :subtitle="__('sepa.proposal.subtitle')">
        <form method="POST" action="{{ route('finance.payment-runs.store') }}" class="space-y-4">
            @csrf

            <div class="grid gap-3 sm:grid-cols-3">
                <x-select-field name="bank_account" :label="__('sepa.column.account')" required>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->sqid }}">{{ $account->label }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="execution_date" type="date" :label="__('sepa.column.execution_date')"
                               :value="old('execution_date', now()->toDateString())"
                               :hint="__('sepa.execution_hint')" />
                <x-input-field name="label" type="text" maxlength="191" :label="__('sepa.column.label')"
                               :value="old('label', '')" />
            </div>

            <x-table scroll="flex" :pin-rows="true" :zebra="true">
                <x-slot:head>
                    <tr>
                        <th class="w-10"></th>
                        <x-table.th sort type="string">{{ __('sepa.column.creditor') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('sepa.column.invoice_number') }}</x-table.th>
                        <x-table.th sort type="date">{{ __('sepa.column.due_date') }}</x-table.th>
                        <x-table.th sort type="date">{{ __('sepa.column.execute_on') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('sepa.column.gross') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('sepa.column.amount') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('sepa.column.note') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($proposals as $proposal)
                    @php($invoice = $proposal['invoice'])
                    <tr class="hover">
                        <td>
                            <input type="checkbox" class="checkbox checkbox-sm" name="invoices[]"
                                   value="{{ $invoice->sqid }}"
                                   @checked($proposal['blocked'] === null)
                                   @disabled($proposal['blocked'] !== null)>
                        </td>
                        <td class="font-medium">{{ $invoice->seller_name ?? '—' }}</td>
                        <td>{{ $invoice->invoice_number ?? '—' }}</td>
                        <td class="whitespace-nowrap">{{ optional($invoice->due_date)->fdate() ?? '—' }}</td>
                        <td class="whitespace-nowrap">{{ $proposal['execute_on']->format('d.m.Y') }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($proposal['gross'], 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums font-medium">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($proposal['amount'], 2, withThousandsSeparator: true) }}</td>
                        <td class="text-xs">
                            @if ($proposal['blocked'] !== null)
                                <x-status-badge tone="error" outline>{{ __('sepa.blocked.' . $proposal['blocked']) }}</x-status-badge>
                            @elseif ($proposal['uses_discount'])
                                <x-status-badge tone="success" outline>{{ __('sepa.discount_used', ['percent' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $proposal['discount_percent'], 2)]) }}</x-status-badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty-state icon="task_alt" :title="__('sepa.proposal.empty')" /></td></tr>
                @endforelse
            </x-table>

            @if ($proposals->isNotEmpty())
                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('sepa.action.create_run') }}</button>
                </div>
            @endif
        </form>
    </x-index-page>
@endsection
