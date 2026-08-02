{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _voucher_link_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Erwartet: $customer, $statement, $vouchers. Hängt eine bereits in Lexoffice
     geführte Pauschalrechnung an den Monat (Feature 098) — der Gegenweg zum
     Push aus workDiary. Der Zahlstatus wird danach sofort nachgezogen. --}}

@php
    $money = fn ($v) => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v instanceof \CommonToolkit\ValueObjects\Money ? $v->toFloat() : (float) $v, 2, withThousandsSeparator: true) . ' €';
@endphp

<x-modal
    :title="__('customer-billing.link_voucher')"
    :eyebrow="$customer->name . ' · ' . $statement->periodLabel()"
    icon="link"
    tone="primary"
    :action="route('customers.billing.retainer.voucher.link', [$customer, $statement])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('customer-billing.link_voucher')">

    <x-form-group :legend="__('customer-billing.lexoffice_voucher')" icon="receipt_long" tone="primary" cols="1"
                  :description="__('customer-billing.link_voucher_hint')">
        @if ($vouchers->isEmpty())
            <p class="text-sm text-base-content/60">{{ __('customer-billing.no_linkable_vouchers') }}</p>
        @else
            <x-select-field name="voucher" :label="__('customer-billing.lexoffice_voucher')" required>
                @foreach ($vouchers as $voucher)
                    <option value="{{ $voucher->sqid }}" @selected($statement->lexoffice_voucher_id === $voucher->id)>
                        {{ $voucher->voucher_date?->fdate() ?? '—' }}
                        · {{ $voucher->voucher_number ?? $voucher->external_id }}
                        · {{ $money($voucher->total_amount) }}
                    </option>
                @endforeach
            </x-select-field>
        @endif
    </x-form-group>
</x-modal>
