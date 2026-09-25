{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Name und Aktivierung einer Vertragsvorlage (MVP-893). --}}
<x-modal :title="__('contract.template.edit')" :eyebrow="$template->kind->label()" icon="contract" tone="primary"
         :action="route('contracts.templates.update', $template)" method="PUT"
         :form-data="['data-entry-form' => '']" :submit-label="__('Speichern')">
    <x-input-field name="name" :label="__('contract.template.name')" :value="old('name', $template->name)" required maxlength="180" />
    <x-checkbox-field name="is_active" :label="__('contract.template.active')" :checked="(bool) old('is_active', $template->is_active)" />
</x-modal>
