{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _voucher_link_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Erwartet: $customer, $statement, $system, $linked, $vouchers (RetainerVoucherRef).
     Hängt eine bereits im Buchhaltungsprogramm geführte Pauschalrechnung an den
     Monat (Feature 098, MVP-1027) — der Gegenweg zum Push aus workDiary. Der
     Zahlstatus wird danach sofort nachgezogen. --}}

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

    <x-form-group :legend="__('customer-billing.channel_voucher', ['system' => $system])" icon="receipt_long" tone="primary" cols="1"
                  :description="__('customer-billing.link_voucher_hint', ['system' => $system])">
        @if ($vouchers === [])
            <p class="text-sm text-muted">{{ __('customer-billing.no_linkable_vouchers', ['system' => $system]) }}</p>
        @else
            <x-select-field name="voucher" :label="__('customer-billing.channel_voucher', ['system' => $system])" required>
                @foreach ($vouchers as $voucher)
                    <option value="{{ $voucher->key }}" @selected($linked?->externalId === $voucher->externalId)>
                        {{ $voucher->date?->fdate() ?? '—' }}
                        · {{ $voucher->number ?? $voucher->externalId }}
                        · {{ $money($voucher->gross) }}
                    </option>
                @endforeach
            </x-select-field>
        @endif
    </x-form-group>
</x-modal>
