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
    $conditionTemplate = ['field' => '', 'op' => 'eq', 'value' => ''];
    $fieldTemplate = ['label' => '', 'type' => \App\Enums\Form\FormFieldType::Text->value, 'required' => false, 'options' => '', 'help' => '', 'unit' => '', 'visible_if' => $conditionTemplate];
    // Bedingung wird intern über den Feld-Key gespeichert, im Editor aber über
    // das Label referenziert → für die Vorbelegung Key→Label zurückübersetzen.
    $keyToLabel = $isEdit
        ? collect($template->fields ?? [])->mapWithKeys(fn($f) => [(string) ($f['key'] ?? '') => (string) ($f['label'] ?? '')])->all()
        : [];
    $fieldItems = old('fields', $isEdit
        ? collect($template->fields ?? [])->map(fn($f) => [
            'label' => (string) ($f['label'] ?? ''),
            'type' => (string) ($f['type'] ?? 'text'),
            'required' => (bool) ($f['required'] ?? false),
            'options' => implode(', ', (array) ($f['options'] ?? [])),
            'help' => (string) ($f['help'] ?? ''),
            'unit' => (string) ($f['unit'] ?? ''),
            'visible_if' => [
                'field' => (string) ($keyToLabel[(string) ($f['visible_if']['field'] ?? '')] ?? ''),
                'op' => (string) ($f['visible_if']['op'] ?? 'eq'),
                'value' => (string) ($f['visible_if']['value'] ?? ''),
            ],
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
        {{-- Gültigkeit + Zuordnung (Feature 032 MVP; Vollaudit 2026-07, M11). --}}
        <x-input-field name="valid_from" type="date" :label="__('form.field.valid_from')" :value="old('valid_from', $template?->valid_from?->toDateString())" />
        <x-input-field name="valid_until" type="date" :label="__('form.field.valid_until')" :value="old('valid_until', $template?->valid_until?->toDateString())" />
        @php
            $targetEntryTypeId = old('target_entry_type') !== null ? null : ($template?->target['entry_type_id'] ?? null);
            $targetCustomerId = old('target_customer') !== null ? null : ($template?->target['customer_id'] ?? null);
        @endphp
        <x-select-field name="target_entry_type" :label="__('form.field.target_entry_type')">
            <option value="">{{ __('alle') }}</option>
            @foreach (\App\Models\EntryType::query()->orderBy('label')->get(['id', 'label']) as $entryType)
                <option value="{{ $entryType->sqid }}" @selected($targetEntryTypeId === $entryType->id)>{{ $entryType->label }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="target_customer" :label="__('form.field.target_customer')">
            <option value="">{{ __('alle') }}</option>
            @foreach (\App\Models\Customer::query()->orderBy('name')->limit(500)->get(['id', 'name']) as $targetCustomer)
                <option value="{{ $targetCustomer->sqid }}" @selected($targetCustomerId === $targetCustomer->id)>{{ $targetCustomer->name }}</option>
            @endforeach
        </x-select-field>
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
                    {{-- Bedingungslogik (Rang 33): Feld nur zeigen, wenn ein anderes
                         Feld einen Wert/Zustand hat. Referenz über Label (Key entsteht
                         serverseitig). --}}
                    <div class="grid grid-cols-1 items-end gap-2 rounded-box bg-base-100/60 p-2 sm:grid-cols-[auto_1fr_auto_1fr]">
                        <label class="fieldset-label text-xs">{{ __('form.condition.legend') }}</label>
                        <select :name="fieldName(i, 'visible_if][field')" x-model="it.visible_if.field"
                                class="select select-xs select-bordered">
                            <option value="">{{ __('form.condition.always') }}</option>
                            <template x-for="other in otherLabeledItems(it)" :key="other.label">
                                <option :value="other.label" x-text="other.label"></option>
                            </template>
                        </select>
                        <select :name="fieldName(i, 'visible_if][op')" x-model="it.visible_if.op"
                                x-show="it.visible_if.field" class="select select-xs select-bordered">
                            <option value="eq">{{ __('form.condition.op.eq') }}</option>
                            <option value="ne">{{ __('form.condition.op.ne') }}</option>
                            <option value="in">{{ __('form.condition.op.in') }}</option>
                            <option value="filled">{{ __('form.condition.op.filled') }}</option>
                        </select>
                        <input type="text" maxlength="500"
                               :name="fieldName(i, 'visible_if][value')" x-model="it.visible_if.value"
                               x-show="it.visible_if.field && it.visible_if.op !== 'filled'"
                               :placeholder="'{{ __('form.condition.value_placeholder') }}'"
                               class="input input-xs input-bordered w-full">
                    </div>
                </div>
            </template>

            <x-icon-btn icon="add" tone="ghost" size="sm" type="button" show-label @click="add()">
                {{ __('form.action.add_field') }}
            </x-icon-btn>
        </div>
    </x-form-group>
</x-modal>
