{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _link_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Vorgang mit der Krisenakte verknüpfen (MVP-218/1605) — Auswahl je Art, nur lesbare Vorgänge aktiver Module. --}}
<x-modal
    :title="__('crisis.link.dialog_title')"
    :eyebrow="$case->title"
    icon="link"
    tone="primary"
    :action="route('crisis.links.store', $case)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Verknüpfen')"
>
    @if ($groups === [])
        <x-empty-state icon="link_off" :title="__('crisis.link.none_available')" compact />
    @else
        <x-select-field name="link_target" :label="__('crisis.link.target')" required :hint="__('crisis.link.target_hint')">
            <option value="">{{ __('Bitte wählen') }}</option>
            @foreach ($groups as $group)
                <optgroup label="{{ $group['label'] }}">
                    @foreach ($group['options'] as $option)
                        <option value="{{ $option['value'] }}" @selected(old('link_target') === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </x-select-field>
        <x-input-field name="note" :label="__('crisis.link.note')" maxlength="500" :value="old('note')" />
    @endif

    <x-validation-errors />
</x-modal>
