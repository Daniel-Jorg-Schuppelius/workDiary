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
        <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>' :title="__('Keine passenden Spesen')" :message="__('Keine genehmigten, weiterberechenbaren Spesen für diesen Kunden gefunden.')" tone="info" compact />
    @else
        <p class="text-sm text-base-content/70">
            {{ __('Wähle die Spesen, die als Position der Rechnung hinzugefügt werden sollen. Brutto-Betrag wird als Einzelpreis übernommen.') }}
        </p>

        <div class="mt-3 overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" class="checkbox checkbox-sm"
                                   onclick="this.closest('table').querySelectorAll('input[name=&quot;expense_ids[]&quot;]').forEach(cb => cb.checked = this.checked)">
                        </th>
                        <th>{{ __('Datum') }}</th>
                        <th>{{ __('Mitarbeiter') }}</th>
                        <th>{{ __('Kategorie') }}</th>
                        <th>{{ __('Beschreibung') }}</th>
                        <th class="text-right">{{ __('Brutto') }}</th>
                    </tr>
                </thead>
                <tbody>
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
                                    <div class="text-xs text-base-content/60">{{ $expense->vendor }}</div>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                {{ number_format((float) $expense->amount_gross, 2, ',', '.') }} {{ $expense->currency->value }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-modal>
