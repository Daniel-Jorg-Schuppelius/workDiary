{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _defect_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\Asset $asset */
@endphp

<x-modal
    :title="__('Defekt melden')"
    :eyebrow="$asset->name ?: $asset->asset_no"
    icon="report"
    tone="error"
    size="md"
    :action="route('assets.defects.store', $asset)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Defekt melden')">

    <label class="form-control">
        <span class="label-text">{{ __('Titel') }}</span>
        <input type="text" name="title" required maxlength="180" value="{{ old('title') }}"
               class="input input-bordered w-full" />
    </label>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="form-control">
            <span class="label-text">{{ __('Schweregrad') }}</span>
            <select name="severity" class="select select-bordered w-full">
                @foreach ($severityOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('severity', 'medium') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control justify-end">
            <span class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="blocks_usage" value="0" />
                <input type="checkbox" name="blocks_usage" value="1" class="checkbox checkbox-error"
                       @checked(old('blocks_usage')) />
                <span class="label-text">{{ __('Asset sperren (kein Checkout möglich)') }}</span>
            </span>
        </label>
    </div>

    <label class="form-control">
        <span class="label-text">{{ __('Beschreibung') }}</span>
        <textarea name="description" rows="3" class="textarea textarea-bordered w-full">{{ old('description') }}</textarea>
    </label>
</x-modal>
