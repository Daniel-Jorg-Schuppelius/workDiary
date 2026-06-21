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
        <x-input-field name="code" :label="__('procedure.field.code')" required maxlength="60" pattern="[A-Za-z0-9_.\-]+" placeholder="{{ __('procedure.hint.code') }}" class="font-mono" :value="old('code')" />
        <x-input-field name="domain" :label="__('procedure.field.domain')" maxlength="40" placeholder="{{ __('procedure.hint.domain') }}" :value="old('domain')" />
        <x-input-field name="name" :label="__('procedure.field.name')" required minlength="3" maxlength="180" span="2" :value="old('name')" />
        <x-textarea-field name="description" :label="__('procedure.field.description')" rows="2" maxlength="2000" span="2" :value="old('description')" />
    </x-form-group>
</x-modal>
