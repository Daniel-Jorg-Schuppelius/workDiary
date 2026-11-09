{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _provider_price_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Einkaufspreise dem letzten Anbieterpreis nachziehen (Phase 175).
  Erwartet: $subscriptions (mit Halter).
--}}
<x-modal
    :title="__('resale.prices.provider.title')"
    icon="price_change"
    size="lg"
    :action="route('finance.resale.prices.provider.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.prices.provider.submit')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.prices.provider.hint') }}</div>
    @if ($subscriptions->isEmpty())
        <x-empty-state compact icon="price_check" :title="__('resale.prices.provider.empty')" />
    @else
        <ul class="text-sm max-h-80 overflow-y-auto space-y-1 border border-base-300 rounded-box px-3 py-2">
            @foreach ($subscriptions as $subscription)
                <li class="flex flex-wrap items-center justify-between gap-2">
                    <x-checkbox-field name="ids[]" :id="'provider-price-' . $subscription->sqid" :value="$subscription->sqid" :toggle="false" :checked="true"
                        :label="$subscription->label . ' · ' . $subscription->holderLabel()" :hint="$subscription->identityLabel()" />
                    <span class="tabular-nums whitespace-nowrap">{{ $subscription->purchase_unit_price->withScale(2)->format() }} → <strong>{{ $subscription->provider_unit_price->withScale(2)->format() }}</strong></span>
                </li>
            @endforeach
        </ul>
    @endif
</x-modal>
