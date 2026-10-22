{{--
  Created on   : Thu May 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _maintenance_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\Asset\Asset $asset */
    $plan ??= null;
    $planKind = $plan?->interval_kind?->value;
@endphp

<x-modal
    :title="$plan ? __('Wartungsplan bearbeiten') : __('Wartungsplan anlegen')"
    :eyebrow="$asset->name ?: $asset->asset_no"
    icon="event_repeat"
    tone="primary"
    size="md"
    :action="$plan ? route('assets.maintenance-plans.update', [$asset, $plan]) : route('assets.maintenance-plans.store', $asset)"
    :method="$plan ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$plan ? __('Speichern') : __('Anlegen')">

    <x-input-field name="label" :label="__('Bezeichnung')" required maxlength="180" :value="old('label', $plan?->label)" />

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="interval_kind" :label="__('Intervall')">
            @foreach ($intervalKindOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('interval_kind', $planKind ?? 'months') === $value)>{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-input-field type="number" name="interval_value" :label="__('Wert')" min="1" :value="old('interval_value', $plan?->interval_value ?? 6)" required />
        <x-input-field type="number" name="tolerance_days" :label="__('Toleranz (Tage)')" min="0" max="365" :value="old('tolerance_days', $plan?->tolerance_days ?? 7)" />
        <x-input-field type="date" name="next_due_on" :label="$plan ? __('Nächste Fälligkeit') : __('Erste Fälligkeit')" :value="old('next_due_on', $plan?->next_due_on?->toDateString())" />
    </div>
</x-modal>
