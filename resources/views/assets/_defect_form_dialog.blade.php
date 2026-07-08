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
    :form-data="['data-entry-form' => '', 'enctype' => 'multipart/form-data']"
    :submit-label="__('Defekt melden')">

    <x-input-field name="title" :label="__('Titel')" required maxlength="180" :value="old('title')" />

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="severity" :label="__('Schweregrad')">
            @foreach ($severityOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('severity', 'medium') === $value)>{{ $label }}</option>
            @endforeach
        </x-select-field>
        <label class="form-control justify-end">
            <span class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="blocks_usage" value="0" />
                <input type="checkbox" name="blocks_usage" value="1" class="checkbox checkbox-error"
                       @checked(old('blocks_usage')) />
                <span class="label-text">{{ __('Asset sperren (kein Checkout möglich)') }}</span>
            </span>
        </label>
    </div>

    <x-textarea-field name="description" :label="__('Beschreibung')" rows="3" :value="old('description')" />

    <label class="form-control">
        <span class="label-text">{{ __('Fotos (optional)') }}</span>
        <input type="file" name="photos[]" accept="image/*" capture="environment" multiple
               class="file-input file-input-bordered file-input-sm" />
        <span class="label-text-alt text-base-content/50">{{ __('JPEG/PNG/WebP, bis 25 MB je Datei.') }}</span>
    </label>
</x-modal>
