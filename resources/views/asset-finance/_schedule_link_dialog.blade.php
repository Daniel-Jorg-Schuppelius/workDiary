{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _schedule_link_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Ratenzeile mit einer Eingangsrechnung referenzieren (MVP-274, D11):
  nur Nachweis, keine Zahlung und keine Buchung.
--}}
<x-modal
    :title="__('Eingangsrechnung referenzieren')"
    :eyebrow="$contract->number . ' · ' . __('Rate fällig am :date', ['date' => $schedule->due_on->fdate()])"
    icon="link"
    :action="$invoices->isEmpty() ? null : route('asset-finance.schedules.link', $schedule)"
    method="POST"
    :submit-label="__('Referenzieren')"
>
    <p class="text-sm text-base-content/70">{{ __('Die Rate gilt damit als bezahlt. Gebucht wird nichts — die Verknüpfung ist nur der Nachweis.') }}</p>

    @if ($invoices->isEmpty())
        <x-empty-state icon="receipt_long" :title="__('Es gibt keine Eingangsrechnung, die sich referenzieren lässt.')" compact />
    @else
        <x-select-field name="incoming_einvoice_id" :label="__('Eingangsrechnung')" required>
            <x-slot:beforeSelect>
                <input type="search" data-select-search="incoming_einvoice_id" autocomplete="off"
                       class="input input-sm input-bordered w-full mb-2"
                       placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}">
            </x-slot:beforeSelect>
            <option value="">—</option>
            @foreach ($invoices as $invoice)
                <option value="{{ $invoice->sqid }}" @selected(old('incoming_einvoice_id', $schedule->incoming_einvoice_id !== null ? \App\Support\Sqid::encode(\App\Models\Invoicing\IncomingEInvoice::class, (int) $schedule->incoming_einvoice_id) : null) === $invoice->sqid)>
                    {{ $invoice->invoice_number ?? '—' }}
                    · {{ $invoice->seller_name ?? '—' }}
                    @if ($invoice->issue_date !== null)
                        · {{ $invoice->issue_date->fdate() }}
                    @endif
                    @if ($invoice->amount_gross !== null)
                        · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($invoice->amount_gross->toFloat(), 2, withThousandsSeparator: true) }} {{ $invoice->amount_gross->getCurrency()->value }}
                    @endif
                </option>
            @endforeach
        </x-select-field>
    @endif
</x-modal>
