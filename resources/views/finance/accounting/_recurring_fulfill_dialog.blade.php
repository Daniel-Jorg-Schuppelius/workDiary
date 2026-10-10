{{--
  Created on   : Fri Oct 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _recurring_fulfill_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Belegerwartung erfüllen (MVP-1102): das eingegangene Original zuordnen.
--}}
<x-modal
    :title="__('accounting.recurring.action.fulfill')"
    :eyebrow="$run->template?->name"
    icon="task_alt"
    :action="route('finance.accounting.recurring.runs.fulfill', $run)"
    method="POST"
    :submit-label="__('accounting.recurring.action.fulfill')"
>
    <p class="text-sm text-base-content/70">
        {{ __('accounting.recurring.fulfill_hint', [
            'period' => $run->period_key,
            'due' => $run->due_on->fdate(),
            'expected' => $run->expected_amount?->format() ?? '—',
        ]) }}
    </p>

    @if ($candidates->isEmpty())
        <x-empty-state icon="receipt_long" :title="__('accounting.recurring.empty.candidates')" compact />
    @else
        <x-select-field name="incoming_einvoice_id" required :label="__('accounting.recurring.field.incoming_einvoice')">
            <option value="">{{ __('accounting.recurring.choose_invoice') }}</option>
            @foreach ($candidates as $incoming)
                <option value="{{ $incoming->sqid }}" @selected(old('incoming_einvoice_id') === $incoming->sqid)>
                    {{ implode(' · ', array_filter([
                        $incoming->invoice_number,
                        $incoming->seller_name,
                        $incoming->issue_date?->fdate(),
                        $incoming->amount_gross?->format(),
                    ])) }}
                </option>
            @endforeach
        </x-select-field>
    @endif
</x-modal>
