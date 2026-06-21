{{-- Shared form fields for PerDiemRate --}}

<x-form-group :legend="__('Pauschalensatz')" icon="restaurant_menu" tone="primary" cols="2">
    <x-input-field name="country" :label="__('Land (ISO-2)')" required maxlength="2" minlength="2"
                   :value="old('country', $rate->country ?? 'DE')"
                   class="uppercase" />
    <x-input-field name="currency" :label="__('Währung')" required maxlength="3" minlength="3"
                   :value="old('currency', $rate->currency ?? 'EUR')"
                   class="uppercase" />
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Region / Stadt') }}</label>
        <input type="text" name="region_label" maxlength="100"
               value="{{ old('region_label', $rate->region_label) }}"
               class="input input-bordered w-full"
               placeholder="{{ __('z. B. New York, Paris, London – leer = Standardtarif des Landes') }}">
        <p class="fieldset-label text-base-content/60">{{ __('Sondertarif für eine Stadt/Region nach BMF-Auslandstabelle. Leer lassen für den Standardtarif des Landes.') }}</p>
    </div>
    <x-input-field type="date" name="valid_from" :label="__('Gültig ab')" required
                   :value="old('valid_from', optional($rate->valid_from)->format('Y-m-d'))" />
    <x-input-field type="date" name="valid_to" :label="__('Gültig bis')"
                   :value="old('valid_to', optional($rate->valid_to)->format('Y-m-d'))" />
    <x-input-field type="number" step="0.01" min="0" name="full_day_amount" :label="__('Vollständiger Tag (Pauschale)')" required
                   :value="old('full_day_amount', $rate->full_day_amount ?? '28.00')" />
    <x-input-field type="number" step="0.01" min="0" name="partial_day_amount" :label="__('Teilweiser Tag (An-/Abreise)')" required
                   :value="old('partial_day_amount', $rate->partial_day_amount ?? '14.00')" />
    <x-input-field type="number" step="0.01" min="0" name="overnight_amount" :label="__('Übernachtungspauschale')"
                   :value="old('overnight_amount', $rate->overnight_amount)" />
    <x-input-field name="source" :label="__('Quelle / Anmerkung')" maxlength="255" span="2"
                   :value="old('source', $rate->source)"
                   :placeholder="__('z. B. BMF-Schreiben 2025')" />
</x-form-group>
