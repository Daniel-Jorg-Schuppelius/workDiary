{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _entry_cost_center_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kostenstelle je Zeile eines Entwurfs nachtragen (Feature 142, MVP-1556) —
  auch an Entwürfen aus der Inbox und aus Fachvorgängen, deren Zeilen sonst
  nicht änderbar sind.
--}}
<x-modal
    :title="__('accounting.entry_form.cost_centers_title')"
    :eyebrow="$entry->memo"
    icon="account_tree"
    :action="route('finance.accounting.journal.cost-centers', $entry)"
    method="PUT"
    :submit-label="__('Speichern')"
>
    @if ($costCenters->isEmpty())
        <x-empty-state icon="account_tree" :title="__('accounting.entry_form.no_cost_centers')" compact />
    @else
        <x-table :bare="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.ledger.column.account') }}</th>
                    <th class="text-right">{{ __('accounting.ledger.column.amount') }}</th>
                    <th>{{ __('accounting.ledger.field.cost_center') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($entry->lines as $line)
                @php($selected = old('cost_centers.' . $line->sqid, $costCenters->firstWhere('id', $line->cost_center_id)?->sqid ?? ''))
                <tr>
                    <td>
                        {{ $line->account?->displayLabel() ?? '—' }}
                        @if ($line->account?->is_cost_center_required)
                            <x-status-badge tone="warning" size="xs">{{ __('accounting.entry_form.cost_center_required') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right font-mono">{{ ($line->debit?->isZero() === false ? $line->debit : $line->credit)?->format() }}</td>
                    <td>
                        <select name="cost_centers[{{ $line->sqid }}]" class="select select-bordered select-sm w-full"
                                aria-label="{{ __('accounting.ledger.field.cost_center') }} {{ $line->line_no }}">
                            <option value="">—</option>
                            @foreach ($costCenters as $costCenter)
                                <option value="{{ $costCenter->sqid }}" @selected($selected === $costCenter->sqid)>{{ $costCenter->code }} · {{ $costCenter->label }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-modal>
