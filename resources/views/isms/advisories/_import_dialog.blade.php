{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Advisory-Import (CSAF/VEX-JSON-Upload). Der Import gleicht gegen das
  Softwareinventar und die letzte Release-SBOM ab und legt
  Schwachstelleneinträge an — Treffer werden NIE automatisch als ausnutzbar
  markiert (044-Regel). Re-Import identischer Dateien ist idempotent.
--}}
<x-modal
    :title="__('isms.action.import_advisory')"
    :eyebrow="__('isms.title.advisories')"
    icon="upload_file"
    tone="primary"
    size="md"
    :action="route('isms.advisories.store')"
    method="POST"
    :form-data="['data-entry-form' => '', 'enctype' => 'multipart/form-data']"
    :submit-label="__('isms.action.import_advisory')">

    <x-form-group :legend="__('isms.group.advisory_import')" icon="upload_file" tone="primary" cols="1">
        <p class="text-xs text-base-content/70">{{ __('isms.advisories.import_hint') }}</p>
        <x-select-field name="format" :label="__('isms.field.format')" required>
            @foreach (\App\Enums\Isms\AdvisoryFormat::cases() as $format)
                <option value="{{ $format->value }}" @selected(old('format', 'csaf') === $format->value)>{{ $format->label() }}</option>
            @endforeach
        </x-select-field>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.advisory_file') }} *</span>
            <input type="file" name="advisory" required accept=".json,application/json"
                   class="file-input file-input-bordered w-full">
            <span class="text-xs text-muted">{{ __('isms.hint.advisory_file') }}</span>
        </label>
    </x-form-group>
</x-modal>
