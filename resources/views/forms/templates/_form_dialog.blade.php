{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlege-/Bearbeitungs-Dialog Formularvorlage (in #entry-modal geladen).
  Felddefinition als dynamische Zeilen über die bestehende Alpine-
  `repeater`-Komponente (Muster: CommunicationNote-Beteiligte).
  Variablen: $template (FormTemplate|null)
--}}
@php
    $isEdit = $template !== null;
    $fieldTemplate = ['label' => '', 'type' => \App\Enums\Form\FormFieldType::Text->value, 'required' => false, 'options' => '', 'help' => '', 'unit' => ''];
    $fieldItems = old('fields', $isEdit
        ? collect($template->fields ?? [])->map(fn($f) => [
            'label' => (string) ($f['label'] ?? ''),
            'type' => (string) ($f['type'] ?? 'text'),
            'required' => (bool) ($f['required'] ?? false),
            'options' => implode(', ', (array) ($f['options'] ?? [])),
            'help' => (string) ($f['help'] ?? ''),
            'unit' => (string) ($f['unit'] ?? ''),
        ])->values()->all()
        : [$fieldTemplate]);
@endphp

<x-modal
    :title="$isEdit ? __('form.action.edit') : __('form.action.create_template')"
    :eyebrow="__('form.title.templates')"
    icon="assignment"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('form-templates.update', $template) : route('form-templates.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('form.action.save') : __('form.action.create_template')">

    <x-form-group :legend="__('form.title.template')" icon="assignment" tone="primary" cols="2">
        <x-input-field name="name" :label="__('form.field.name')" required minlength="3" maxlength="160" span="2" :value="old('name', $template?->name)" />
        <x-textarea-field name="description" :label="__('form.field.description')" rows="2" maxlength="2000" span="2" :value="old('description', $template?->description)" />
    </x-form-group>

    <x-form-group :legend="__('form.field.fields')" icon="list_alt" tone="info">
        @error('fields')
            <p class="text-error text-sm sm:col-span-2">{{ $message }}</p>
        @enderror
        <div x-data="repeater"
             data-prefix="fields"
             data-items="{{ json_encode($fieldItems) }}"
             data-template="{{ json_encode($fieldTemplate) }}"
             class="space-y-2 sm:col-span-2">
            <template x-for="(it, i) in items" :key="i">
                <div class="space-y-2 rounded-box border border-base-300 bg-base-200/40 p-3">
                    <div class="grid grid-cols-1 items-end gap-2 sm:grid-cols-[1fr_auto_auto_auto]">
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('form.field.field_label') }}</label>
                            <input type="text" maxlength="160"
                                   :name="fieldName(i, 'label')" x-model="it.label"
                                   class="input input-sm input-bordered w-full">
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('form.field.field_type') }}</label>
                            <select :name="fieldName(i, 'type')" x-model="it.type"
                                    class="select select-sm select-bordered">
                                @foreach (\App\Enums\Form\FormFieldType::cases() as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="label cursor-pointer gap-2 pb-1">
                            <input type="checkbox" class="checkbox checkbox-sm"
                                   :name="fieldName(i, 'required')" x-model="it.required">
                            <span class="label-text text-xs">{{ __('form.field.field_required') }}</span>
                        </label>
                        <x-icon-btn icon="close" tone="error" size="xs" type="button"
                                    :label="__('form.action.remove_field')" @click="remove(i)" />
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div class="fieldset" x-show="it.type === 'select'">
                            <label class="fieldset-label">{{ __('form.field.field_options') }}</label>
                            <input type="text" maxlength="2000"
                                   placeholder="{{ __('form.hint.options') }}"
                                   :name="fieldName(i, 'options')" x-model="it.options"
                                   class="input input-sm input-bordered w-full">
                        </div>
                        <div class="fieldset" x-show="it.type === 'number'">
                            <label class="fieldset-label">{{ __('form.field.field_unit') }}</label>
                            <input type="text" maxlength="20"
                                   placeholder="{{ __('form.hint.unit') }}"
                                   :name="fieldName(i, 'unit')" x-model="it.unit"
                                   class="input input-sm input-bordered w-full">
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('form.field.field_help') }}</label>
                            <input type="text" maxlength="500"
                                   :name="fieldName(i, 'help')" x-model="it.help"
                                   class="input input-sm input-bordered w-full">
                        </div>
                    </div>
                </div>
            </template>

            <x-icon-btn icon="add" tone="ghost" size="sm" type="button" show-label @click="add()">
                {{ __('form.action.add_field') }}
            </x-icon-btn>
        </div>
    </x-form-group>
</x-modal>
