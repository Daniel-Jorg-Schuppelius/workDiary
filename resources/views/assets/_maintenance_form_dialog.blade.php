{{--
  Created on   : Thu May 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _maintenance_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\Asset $asset */
@endphp

<x-modal
    :title="__('Wartungsplan anlegen')"
    :eyebrow="$asset->name ?: $asset->asset_no"
    icon="event_repeat"
    tone="primary"
    size="md"
    :action="route('assets.maintenance-plans.store', $asset)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <label class="form-control">
        <span class="label-text">{{ __('Bezeichnung') }}</span>
        <input type="text" name="label" required maxlength="180"
               value="{{ old('label') }}" class="input input-bordered w-full" />
    </label>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="form-control">
            <span class="label-text">{{ __('Intervall') }}</span>
            <select name="interval_kind" class="select select-bordered w-full">
                @foreach ($intervalKindOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('interval_kind', 'months') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('Wert') }}</span>
            <input type="number" name="interval_value" min="1" value="{{ old('interval_value', 6) }}"
                   class="input input-bordered w-full" required />
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('Toleranz (Tage)') }}</span>
            <input type="number" name="tolerance_days" min="0" max="365" value="{{ old('tolerance_days', 7) }}"
                   class="input input-bordered w-full" />
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('Erste Fälligkeit') }}</span>
            <input type="date" name="next_due_on" value="{{ old('next_due_on') }}" class="input input-bordered w-full" />
        </label>
    </div>
</x-modal>
