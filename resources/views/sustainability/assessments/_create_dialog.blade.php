{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _create_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Bewertung starten (Feature 071, MVP-1607) — mit Bezugsobjekt aus der Auswahl oder frei benannt. --}}
<x-modal
    :title="__('Bewertung starten')"
    :eyebrow="__('Nachhaltigkeit')"
    icon="grade"
    tone="primary"
    :action="route('sustainability.assessments.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Bewertung starten')"
>
    @if ($groups !== [])
        <x-select-field name="subject_ref" :label="__('sustainability.subject.label')" :hint="__('sustainability.subject.hint')">
            <option value="">{{ __('sustainability.subject.none') }}</option>
            @foreach ($groups as $group)
                <optgroup label="{{ $group['label'] }}">
                    @foreach ($group['options'] as $option)
                        <option value="{{ $option['value'] }}" @selected(old('subject_ref', $preset) === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </x-select-field>
    @endif
    <x-input-field name="subject_label" maxlength="200" :label="__('sustainability.subject.free_label')"
                   :hint="__('sustainability.subject.free_label_hint')" :value="old('subject_label')" />
    <p class="text-xs text-muted">{{ __(':count aktive Kriterien im Katalog.', ['count' => $criteriaCount]) }}</p>

    <x-validation-errors />
</x-modal>
