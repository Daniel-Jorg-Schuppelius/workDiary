{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _entry_rows.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zeilenmaske einer Buchung von Hand (MVP-1556/1558) — geteilt von
  Handbuchung, Entwurf bearbeiten und Buchungsvorlage. Erwartet $rows,
  $rowCount, $counterparty, $accounts, $taxCodes, $costCenters, $customers,
  $suppliers. Auswertung: App\Services\Accounting\ManualEntryLines.
--}}
@php($selectedParty = old('counterparty', $counterparty ?? ''))
<x-select-field name="counterparty" :label="__('accounting.entry_form.counterparty')" :hint="__('accounting.entry_form.counterparty_hint')">
    <option value="">—</option>
    <optgroup label="{{ \App\Support\EntityType::label(\App\Models\Customer\Customer::class) }}">
        @foreach ($customers as $customer)
            <option value="customer:{{ $customer->sqid }}" @selected($selectedParty === 'customer:' . $customer->sqid)>{{ $customer->name }}@if ($customer->number) · {{ $customer->number }}@endif</option>
        @endforeach
    </optgroup>
    <optgroup label="{{ \App\Support\EntityType::label(\App\Models\Supplier\Supplier::class) }}">
        @foreach ($suppliers as $supplier)
            <option value="supplier:{{ $supplier->sqid }}" @selected($selectedParty === 'supplier:' . $supplier->sqid)>{{ $supplier->name }}@if ($supplier->number) · {{ $supplier->number }}@endif</option>
        @endforeach
    </optgroup>
</x-select-field>

<datalist id="entry-form-accounts">
    @foreach ($accounts as $account)
        <option value="{{ $account->number }}">{{ $account->name }}</option>
    @endforeach
</datalist>

<p class="text-xs text-muted">{{ __('accounting.entry_form.rows_hint') }}</p>

<x-table :bare="true">
    <x-slot:head>
        <tr>
            <th>{{ __('accounting.ledger.column.account') }}</th>
            <th class="text-right">{{ __('accounting.ledger.column.debit') }}</th>
            <th class="text-right">{{ __('accounting.ledger.column.credit') }}</th>
            <th>{{ __('accounting.entry_form.tax_code') }}</th>
            @if ($costCenters->isNotEmpty())
                <th>{{ __('accounting.ledger.field.cost_center') }}</th>
            @endif
            <th>{{ __('accounting.entry_form.line_memo') }}</th>
        </tr>
    </x-slot:head>
    @for ($i = 0; $i < $rowCount; $i++)
        @php($row = $rows[$i] ?? null)
        @php($rowAccount = old("rows.$i.account", $row ? ($accounts->firstWhere('id', $row['account_id'])?->number ?? '') : ''))
        @php($rowTaxCode = old("rows.$i.tax_code", $row && $row['tax_code_id'] ? ($taxCodes->firstWhere('id', $row['tax_code_id'])?->sqid ?? '') : ''))
        @php($rowCostCenter = old("rows.$i.cost_center", $row && $row['cost_center_id'] ? ($costCenters->firstWhere('id', $row['cost_center_id'])?->sqid ?? '') : ''))
        <tr>
            <td>
                @if ($row)
                    <input type="hidden" name="rows[{{ $i }}][line]" value="{{ $row['line'] }}">
                @endif
                <input type="text" name="rows[{{ $i }}][account]" list="entry-form-accounts" maxlength="16"
                       value="{{ $rowAccount }}" autocomplete="off"
                       class="input input-bordered input-sm w-28 font-mono"
                       aria-label="{{ __('accounting.ledger.column.account') }} {{ $i + 1 }}">
            </td>
            <td class="text-right">
                <input type="number" step="0.01" min="0" name="rows[{{ $i }}][debit]"
                       value="{{ old("rows.$i.debit", $row['debit'] ?? '') }}"
                       class="input input-bordered input-sm w-28 text-right"
                       aria-label="{{ __('accounting.ledger.column.debit') }} {{ $i + 1 }}">
            </td>
            <td class="text-right">
                <input type="number" step="0.01" min="0" name="rows[{{ $i }}][credit]"
                       value="{{ old("rows.$i.credit", $row['credit'] ?? '') }}"
                       class="input input-bordered input-sm w-28 text-right"
                       aria-label="{{ __('accounting.ledger.column.credit') }} {{ $i + 1 }}">
            </td>
            <td>
                <select name="rows[{{ $i }}][tax_code]" class="select select-bordered select-sm w-36"
                        aria-label="{{ __('accounting.entry_form.tax_code') }} {{ $i + 1 }}">
                    <option value="">—</option>
                    @foreach ($taxCodes as $taxCode)
                        <option value="{{ $taxCode->sqid }}" @selected($rowTaxCode === $taxCode->sqid)>{{ $taxCode->code }} · {{ $taxCode->rate }} %</option>
                    @endforeach
                </select>
            </td>
            @if ($costCenters->isNotEmpty())
                <td>
                    <select name="rows[{{ $i }}][cost_center]" class="select select-bordered select-sm w-36"
                            aria-label="{{ __('accounting.ledger.field.cost_center') }} {{ $i + 1 }}">
                        <option value="">—</option>
                        @foreach ($costCenters as $costCenter)
                            <option value="{{ $costCenter->sqid }}" @selected($rowCostCenter === $costCenter->sqid)>{{ $costCenter->code }} · {{ $costCenter->label }}</option>
                        @endforeach
                    </select>
                </td>
            @endif
            <td>
                <input type="text" name="rows[{{ $i }}][memo]" maxlength="191"
                       value="{{ old("rows.$i.memo", $row['memo'] ?? '') }}"
                       class="input input-bordered input-sm w-full"
                       aria-label="{{ __('accounting.entry_form.line_memo') }} {{ $i + 1 }}">
            </td>
        </tr>
    @endfor
</x-table>
