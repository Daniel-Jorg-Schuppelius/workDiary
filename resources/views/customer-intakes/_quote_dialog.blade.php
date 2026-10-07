{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _quote_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: bestehendes Angebot des Kunden verknüpfen (MVP-1075). Erwartet: $intake, $quotes --}}
<x-modal
    :title="__('customer_intake.action.link_quote')"
    :eyebrow="$intake->number"
    icon="request_quote"
    :action="route('customer-intakes.quote.link', $intake)"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('customer_intake.action.link_quote')"
>
    <x-form-group :legend="__('customer_intake.quote.existing')" icon="link" cols="1">
        <x-select-field name="quote_id" :label="__('Angebot')" required>
            <option value="">…</option>
            @foreach ($quotes as $quote)
                <option value="{{ $quote->sqid }}" @selected((string) old('quote_id') === $quote->sqid)>
                    {{ $quote->number }} · {{ __('customer_intake.quote.version', ['version' => $quote->version]) }} · {{ $quote->status->label() }}{{ $quote->total !== null ? ' · ' . $quote->total->format() : '' }}
                </option>
            @endforeach
        </x-select-field>
        @if ($quotes->isEmpty())
            <p class="text-sm text-muted">{{ __('customer_intake.quote.none_for_customer') }}</p>
        @endif
    </x-form-group>

    <x-validation-errors />
</x-modal>
