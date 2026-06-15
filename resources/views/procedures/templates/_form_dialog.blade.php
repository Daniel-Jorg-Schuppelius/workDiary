{{--
  Created on   : Sun Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlage-Dialog Prozedurvorlage (in #entry-modal geladen). Nur Stammdaten;
  die Schritte werden anschließend im Voll-Seiten-Designer (edit) gepflegt.
  Variable: $template (immer null – nur Anlage über Modal)
--}}
<x-modal
    :title="__('procedure.action.createTemplate')"
    :eyebrow="__('procedure.title.templates')"
    icon="rule"
    tone="primary"
    size="md"
    :action="route('procedures.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('procedure.action.createTemplate')">

    <x-form-group :legend="__('procedure.title.template')" icon="rule" tone="primary" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('procedure.field.code') }} *</span>
            <input type="text" name="code" required maxlength="60"
                   pattern="[A-Za-z0-9_.\-]+"
                   placeholder="{{ __('procedure.hint.code') }}"
                   class="input input-bordered w-full font-mono" value="{{ old('code') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('procedure.field.domain') }}</span>
            <input type="text" name="domain" maxlength="40"
                   placeholder="{{ __('procedure.hint.domain') }}"
                   class="input input-bordered w-full" value="{{ old('domain') }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('procedure.field.name') }} *</span>
            <input type="text" name="name" required minlength="3" maxlength="180"
                   class="input input-bordered w-full" value="{{ old('name') }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('procedure.field.description') }}</span>
            <textarea name="description" rows="2" maxlength="2000"
                      class="textarea textarea-bordered w-full">{{ old('description') }}</textarea>
        </label>
    </x-form-group>
</x-modal>
