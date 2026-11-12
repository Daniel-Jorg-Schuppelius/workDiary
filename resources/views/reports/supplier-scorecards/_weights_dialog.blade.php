{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _weights_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $weights (wirksame Gewichte als Anteil), $defaults (Vorgaben als Anteil) --}}
<x-modal
    :title="__('scorecard.weights.title')"
    :eyebrow="__('scorecard.title')"
    icon="tune"
    tone="primary"
    :action="route('supplier-scorecards.weights.update')"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <input type="hidden" name="_dialog_url" value="{{ route('supplier-scorecards.weights.edit') }}?dialog=1">

    <x-form-group :legend="__('scorecard.weights.title')" icon="tune" tone="primary" cols="2"
                  :description="__('scorecard.weights.description')">
        @foreach ($defaults as $key => $default)
            <x-input-field name="weights[{{ $key }}]" type="number" min="0" max="100" step="0.01" inputmode="decimal"
                           :label="__('scorecard.metric_' . $key)"
                           error="weights.{{ $key }}"
                           :value="old('weights.' . $key, round($weights[$key] * 100, 2))"
                           :placeholder="__('settings.placeholder_default', ['value' => round($default * 100, 2)])" />
        @endforeach
    </x-form-group>
    @error('weights')
        <p class="text-error text-sm">{{ $message }}</p>
    @enderror
</x-modal>
