{{--
  Created on   : Thu Jul 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _agreement_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Erwartet: $customer, $agreement (nullable), $activityCategories.
     Satzzeilen als parallele Arrays (rate_*[]) — Zeilen ohne Stundensatz
     werden serverseitig ignoriert; drei Leerzeilen für neue Sätze. --}}

@php
    $rates = $agreement?->rates ?? collect();
    $extraRows = 3;
    // Nach einem Validierungsfehler kommen die Kategorien als Sqids zurück,
    // sonst als IDs aus dem Profil — beides auf Sqid normiert.
    $selectedTravelCategories = is_array(old('travel_categories'))
        ? array_map('strval', old('travel_categories'))
        : $activityCategories->whereIn('id', $agreement?->travel_categories ?? [])->pluck('sqid')->all();
@endphp

<x-modal
    :title="$agreement ? __('customer-billing.edit_agreement') : __('customer-billing.create_agreement')"
    :eyebrow="$customer->name"
    icon="request_quote"
    tone="primary"
    :action="route('customers.billing.agreement.save', $customer)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <x-form-group :legend="__('customer-billing.agreement')" icon="tune" tone="primary" cols="2">
        <x-select-field name="mode" :label="__('customer-billing.mode')" required
                        :hint="__('customer-billing.mode_hint')">
            @foreach (\App\Enums\Billing\BillingAgreementMode::options() as $value => $label)
                <option value="{{ $value }}" @selected(old('mode', $agreement?->mode?->value ?? 'account') === $value)>{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="workdays_per_week" :label="__('customer-billing.workdays_per_week')" required
                        :hint="__('customer-billing.workdays_hint')">
            @foreach ([5, 6, 7] as $days)
                <option value="{{ $days }}" @selected((int) old('workdays_per_week', $agreement?->workdays_per_week ?? 6) === $days)>{{ $days }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="expected_monthly_amount" type="number" step="0.01" min="0"
                       :label="__('customer-billing.expected_monthly')"
                       :hint="__('customer-billing.expected_monthly_hint')"
                       :value="old('expected_monthly_amount', $agreement?->expected_monthly_amount?->getAmount())" />
        <x-select-field name="currency" :label="__('Währung')" required>
            <x-currency-options :selected="old('currency', $agreement?->currency?->value ?? 'EUR')" />
        </x-select-field>
        <x-checkbox-field name="active" :label="__('Aktiv')" :checked="(bool) old('active', $agreement?->active ?? true)" />
    </x-form-group>

    <x-form-group :legend="__('customer-billing.opening_balance')" icon="account_balance" tone="info" cols="2"
                  :description="__('customer-billing.opening_balance_hint')">
        <x-input-field name="opening_balance" type="number" step="0.01"
                       :label="__('customer-billing.opening_balance')"
                       :value="old('opening_balance', $agreement?->opening_balance?->getAmount() ?? 0)" />
        <x-input-field name="opening_balance_date" type="date"
                       :label="__('customer-billing.opening_balance_date')"
                       :value="old('opening_balance_date', $agreement?->opening_balance_date?->toDateString())" />
    </x-form-group>

    <x-form-group :legend="__('customer-billing.rates')" icon="payments" tone="warning" cols="1"
                  :description="__('customer-billing.rates_hint')">
        <div class="grid grid-cols-[1fr_8rem_7rem] gap-2 text-xs font-medium text-base-content/60">
            <span>{{ __('customer-billing.activity_category') }}</span>
            <span>{{ __('customer-billing.day_type') }}</span>
            <span>{{ __('customer-billing.hourly_rate') }}</span>
        </div>
        @foreach ($rates as $rate)
            <div class="grid grid-cols-[1fr_8rem_7rem] gap-2">
                <x-select-field name="rate_activity_category_id[]">
                    <option value="">{{ __('customer-billing.all_categories') }}</option>
                    @foreach ($activityCategories as $category)
                        <option value="{{ $category->sqid }}" @selected($rate->activity_category_id === $category->id)>{{ $category->label }}</option>
                    @endforeach
                </x-select-field>
                <x-select-field name="rate_day_type[]">
                    @foreach (\App\Enums\Billing\BillingRateDayType::options() as $value => $label)
                        <option value="{{ $value }}" @selected($rate->day_type->value === $value)>{{ $label }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="rate_hourly_rate[]" type="number" step="0.01" min="0"
                               :value="$rate->hourly_rate?->getAmount()" />
            </div>
        @endforeach
        @for ($i = 0; $i < $extraRows; $i++)
            <div class="grid grid-cols-[1fr_8rem_7rem] gap-2">
                <x-select-field name="rate_activity_category_id[]">
                    <option value="">{{ __('customer-billing.all_categories') }}</option>
                    @foreach ($activityCategories as $category)
                        <option value="{{ $category->sqid }}">{{ $category->label }}</option>
                    @endforeach
                </x-select-field>
                <x-select-field name="rate_day_type[]">
                    @foreach (\App\Enums\Billing\BillingRateDayType::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="rate_hourly_rate[]" type="number" step="0.01" min="0" />
            </div>
        @endfor
    </x-form-group>

    <x-form-group :legend="__('customer-billing.travel_flat')" icon="directions_car" tone="info" cols="2"
                  :description="__('customer-billing.travel_flat_hint')">
        <x-input-field name="travel_minutes_per_entry" type="number" min="0" max="480" step="5"
                       :label="__('customer-billing.travel_minutes_per_entry')"
                       :hint="__('customer-billing.travel_minutes_hint')"
                       :value="old('travel_minutes_per_entry', $agreement?->travel_minutes_per_entry ?? 0)" />
        <x-checkbox-field name="holidays_as_weekend"
                          :label="__('customer-billing.holidays_as_weekend')"
                          :hint="__('customer-billing.holidays_as_weekend_hint')"
                          :checked="(bool) old('holidays_as_weekend', $agreement?->holidays_as_weekend ?? false)" />
        <div class="md:col-span-2">
            <p class="fieldset-label">{{ __('customer-billing.travel_categories') }}</p>
            <p class="text-xs text-base-content/60 mb-2">{{ __('customer-billing.travel_categories_hint') }}</p>
            <div class="flex flex-wrap gap-x-6">
                @foreach ($activityCategories as $category)
                    <x-checkbox-field name="travel_categories[]" :value="$category->sqid" :toggle="false"
                                      :with-hidden="false" :label="$category->label"
                                      :checked="in_array($category->sqid, $selectedTravelCategories, true)" />
                @endforeach
            </div>
        </div>
    </x-form-group>

    <x-form-group :legend="__('Notizen')" icon="notes" tone="ghost" cols="1">
        <x-textarea-field name="notes" rows="2" maxlength="2000" :value="old('notes', $agreement?->notes)" />
    </x-form-group>
</x-modal>
