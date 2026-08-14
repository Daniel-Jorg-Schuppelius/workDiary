{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _exercise_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Modal (Feature 070): Übung planen. Übungen/Tests verfälschen nie echte
  Krisenakten. Variablen: $templates (Collection<ProcedureTemplate>).
--}}
<x-modal
    :title="__('Übung planen')"
    :eyebrow="__('Krisenübungen')"
    icon="model_training"
    tone="primary"
    :action="route('crisis.exercises.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Planen')">

    <x-input-field name="title" :label="__('Titel')" required maxlength="200" autofocus :value="old('title')" />

    <x-select-field name="playbook_template_id" :label="__('Playbook/Prozedur')">
        <option value="">{{ __('— Playbook/Prozedur (optional) —') }}</option>
        @foreach ($templates as $template)
            <option value="{{ $template->sqid }}" @selected(old('playbook_template_id') === $template->sqid)>{{ $template->name }}</option>
        @endforeach
    </x-select-field>

    <x-textarea-field name="scenario" :label="__('Szenario')" required rows="3" maxlength="10000" :value="old('scenario')" />

    <x-input-field name="next_due_on" type="date" :label="__('Geplant für')" :value="old('next_due_on')" />
</x-modal>
