{{-- Shared form fields for PerDiemRate --}}

<x-form-group :legend="__('Pauschalensatz')" icon="restaurant_menu" tone="primary" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Land (ISO-2)') }} *</label>
        <input type="text" name="country" required maxlength="2" minlength="2"
               value="{{ old('country', $rate->country ?? 'DE') }}"
               class="input input-bordered w-full uppercase">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Währung') }} *</label>
        <input type="text" name="currency" required maxlength="3" minlength="3"
               value="{{ old('currency', $rate->currency ?? 'EUR') }}"
               class="input input-bordered w-full uppercase">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Gültig ab') }} *</label>
        <input type="date" name="valid_from" required
               value="{{ old('valid_from', optional($rate->valid_from)->format('Y-m-d')) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Gültig bis') }}</label>
        <input type="date" name="valid_to"
               value="{{ old('valid_to', optional($rate->valid_to)->format('Y-m-d')) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Vollständiger Tag (Pauschale)') }} *</label>
        <input type="number" step="0.01" min="0" name="full_day_amount" required
               value="{{ old('full_day_amount', $rate->full_day_amount ?? '28.00') }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Teilweiser Tag (An-/Abreise)') }} *</label>
        <input type="number" step="0.01" min="0" name="partial_day_amount" required
               value="{{ old('partial_day_amount', $rate->partial_day_amount ?? '14.00') }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Übernachtungspauschale') }}</label>
        <input type="number" step="0.01" min="0" name="overnight_amount"
               value="{{ old('overnight_amount', $rate->overnight_amount) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Quelle / Anmerkung') }}</label>
        <input type="text" name="source" maxlength="255"
               value="{{ old('source', $rate->source) }}"
               class="input input-bordered w-full"
               placeholder="{{ __('z. B. BMF-Schreiben 2025') }}">
    </div>
</x-form-group>
