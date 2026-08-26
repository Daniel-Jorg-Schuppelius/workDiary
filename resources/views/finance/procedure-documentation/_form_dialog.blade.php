{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Freitext-Dialog des Entwurfs (in #entry-modal geladen). Variablen: $document
--}}
<x-modal
    :title="__('procedure-documentation.dialog.edit_title', ['version' => $document->displayVersion()])"
    :eyebrow="__('procedure-documentation.title')"
    icon="menu_book"
    tone="primary"
    size="wide"
    :action="route('finance.procedure-documentation.update', $document)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('procedure-documentation.action.save')">

    <x-form-group :legend="__('procedure-documentation.dialog.legend')" :description="__('procedure-documentation.dialog.description')" icon="description" tone="primary">
        @foreach (\App\Models\Finance\ProcedureDocumentation::TEXT_FIELDS as $field)
            <x-textarea-field :name="$field" :label="__('procedure-documentation.text.' . $field)" :hint="__('procedure-documentation.hint.' . $field)" rows="6">{{ old($field, $document->{$field}) }}</x-textarea-field>
        @endforeach
    </x-form-group>
</x-modal>
