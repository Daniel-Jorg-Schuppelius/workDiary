{{--
  Created on   : Fri May 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _attach_expenses_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $invoice, $expenses (Collection<Expense>) --}}
<x-modal
    :title="__('Spesen zur Rechnung hinzufügen')"
    :eyebrow="$invoice->number"
    icon="receipt_long"
    tone="primary"
    :action="route('invoices.expenses.attach', $invoice)"
    method="POST"
    :submit-label="__('Hinzufügen')"
    size="lg">

    @if ($expenses->isEmpty())
        <x-empty-state icon="receipt_long" :title="__('Keine passenden Spesen')" :message="__('Keine genehmigten, weiterberechenbaren Spesen für diesen Kunden gefunden.')" tone="info" compact />
    @else
        <p class="text-sm text-base-content/70">
            {{ __('Wähle die Spesen, die als Position der Rechnung hinzugefügt werden sollen. Brutto-Betrag wird als Einzelpreis übernommen.') }}
        </p>

        <x-table class="mt-3">
            <x-slot:head>
                    <tr>
                        <th>
                            <input type="checkbox" class="checkbox checkbox-sm"
                                   data-check-all='input[name="expense_ids[]"]'>
                        </th>
                        <th>{{ __('Datum') }}</th>
                        <th>{{ __('Mitarbeiter') }}</th>
                        <th>{{ __('Kategorie') }}</th>
                        <th>{{ __('Beschreibung') }}</th>
                        <th class="text-right">{{ __('Brutto') }}</th>
                    </tr>
            </x-slot:head>
                    @foreach ($expenses as $expense)
                        <tr>
                            <td>
                                <input type="checkbox" name="expense_ids[]" value="{{ $expense->sqid }}"
                                       class="checkbox checkbox-sm" checked>
                            </td>
                            <td class="whitespace-nowrap">{{ $expense->date->fdate() }}</td>
                            <td>{{ $expense->user?->name ?? '—' }}</td>
                            <td>
                                @if ($expense->category)
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon :name="$expense->category->icon ?: 'receipt_long'"
                                                class="text-{{ $expense->category->color ?: 'primary' }}" />
                                        {{ $expense->category->label }}
                                    </span>
                                @endif
                            </td>
                            <td class="max-w-xs truncate">
                                {{ $expense->description }}
                                @if ($expense->vendor)
                                    <div class="text-xs text-muted">{{ $expense->vendor }}</div>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($expense->amount_gross?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} {{ $expense->currency->value }}
                            </td>
                        </tr>
                    @endforeach
        </x-table>
    @endif
</x-modal>
