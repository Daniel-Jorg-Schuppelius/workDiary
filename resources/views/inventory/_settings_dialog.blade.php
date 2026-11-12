{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _settings_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $valuationMethod (ValuationMethod), $reservationTiming (string), $reservationTimings (list<string>) --}}
<x-modal
    :title="__('inventory.settings.title')"
    :eyebrow="__('inventory.stock')"
    icon="settings"
    tone="primary"
    :action="route('inventory.settings.update')"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <input type="hidden" name="_dialog_url" value="{{ route('inventory.settings.edit') }}?dialog=1">

    <x-form-group :legend="__('inventory.settings.valuation.heading')" icon="calculate" tone="primary" cols="1"
                  :description="__('inventory.settings.valuation.description')">
        <x-select-field name="valuation_method" :label="__('inventory.settings.valuation.label')" required
                        :hint="__('inventory.settings.valuation.hint')">
            @foreach (\App\Enums\Inventory\ValuationMethod::cases() as $method)
                <option value="{{ $method->value }}" @selected(old('valuation_method', $valuationMethod->value) === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('inventory.settings.reservation.heading')" icon="event_available" tone="primary" cols="1"
                  :description="__('inventory.settings.reservation.description')">
        <x-select-field name="reservation_timing" :label="__('inventory.settings.reservation.label')" required
                        :hint="__('inventory.settings.reservation.hint')">
            @foreach ($reservationTimings as $timing)
                <option value="{{ $timing }}" @selected(old('reservation_timing', $reservationTiming) === $timing)>{{ __('inventory.settings.reservation.option.' . $timing) }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    {{-- Vier-Augen-Freigabe der Inventurdifferenzen (MVP-1585). --}}
    <x-form-group :legend="__('inventory.settings.four_eyes.heading')" icon="verified_user" tone="primary" cols="2"
                  :description="__('inventory.settings.four_eyes.description')">
        <label class="label cursor-pointer justify-start gap-3 self-end">
            <input type="hidden" name="four_eyes_count" value="0">
            <input type="checkbox" name="four_eyes_count" value="1" class="checkbox checkbox-sm" @checked((bool) old('four_eyes_count', $fourEyesCount))>
            <span class="label-text">{{ __('inventory.settings.four_eyes.enabled') }}</span>
        </label>
        <x-input-field name="four_eyes_count_threshold" id="four-eyes-count-threshold" type="number" min="0" step="0.01" inputmode="decimal"
                       :label="__('inventory.settings.four_eyes.threshold')" :hint="__('inventory.settings.four_eyes.threshold_hint')"
                       :value="old('four_eyes_count_threshold', \CommonToolkit\Helper\Data\NumberHelper::roundPrecise($fourEyesThreshold, 2))" />
        {{-- Negativbestand (MVP-1626): bucht erst nach Freigabe einer zweiten Person im Genehmigungs-Eingang. --}}
        <label class="label cursor-pointer justify-start gap-3 sm:col-span-2">
            <input type="hidden" name="four_eyes_negative" value="0">
            <input type="checkbox" name="four_eyes_negative" value="1" class="checkbox checkbox-sm" @checked((bool) old('four_eyes_negative', $fourEyesNegative ?? false))>
            <span class="label-text">{{ __('inventory.settings.four_eyes.negative') }}</span>
        </label>
        <p class="text-xs text-muted sm:col-span-2">{{ __('inventory.settings.four_eyes.negative_hint') }}</p>
    </x-form-group>

    {{-- SKU-/GTIN-Hoheit (MVP-1583): Anzeige; gewählt wird beim Nummernkreis „Artikel“. --}}
    <x-form-group :legend="__('inventory.settings.numbers.heading')" icon="tag" tone="primary" cols="1"
                  :description="__('inventory.settings.numbers.description')">
        <x-detail-grid>
            <x-detail-grid.row :label="__('inventory.settings.numbers.label')">{{ $articleNumberSource }}</x-detail-grid.row>
        </x-detail-grid>
        @can('manage', \App\Models\Numbering\NumberFormat::class)
            <a class="link text-sm" href="{{ route('admin.number-formats.index') }}">{{ __('inventory.settings.numbers.link') }}</a>
        @endcan
    </x-form-group>
</x-modal>
