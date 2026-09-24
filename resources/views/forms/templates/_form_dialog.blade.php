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
    $fieldTemplate = ['label' => '', 'type' => \App\Enums\Fields\FieldType::Text->value, 'required' => false, 'options' => '', 'help' => '', 'unit' => '', 'min' => '', 'max' => '', 'visible_if' => $conditionTemplate];
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
            'min' => (string) ($f['min'] ?? ''),
            'max' => (string) ($f['max'] ?? ''),
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
        <x-date-range layout="split" grid-class="contents"
                      from-name="valid_from" to-name="valid_until" type="date"
                      :from="old('valid_from', $template?->valid_from?->toDateString())"
                      :to="old('valid_until', $template?->valid_until?->toDateString())"
                      :from-label="__('form.field.valid_from')" :to-label="__('form.field.valid_until')" />
        @php
            $targetEntryTypeId = old('target_entry_type') !== null ? null : ($template?->target['entry_type_id'] ?? null);
            $targetCustomerId = old('target_customer') !== null ? null : ($template?->target['customer_id'] ?? null);
        @endphp
        <x-select-field name="target_entry_type" :label="__('form.field.target_entry_type')">
            <option value="">{{ __('alle') }}</option>
            @foreach (\App\Models\Classification\EntryType::query()->orderBy('label')->get(['id', 'label']) as $entryType)
                <option value="{{ $entryType->sqid }}" @selected($targetEntryTypeId === $entryType->id)>{{ $entryType->label }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="target_customer" :label="__('form.field.target_customer')">
            <option value="">{{ __('alle') }}</option>
            @foreach (\App\Models\Customer\Customer::query()->orderBy('name')->limit(500)->get(['id', 'name']) as $targetCustomer)
                <option value="{{ $targetCustomer->sqid }}" @selected($targetCustomerId === $targetCustomer->id)>{{ $targetCustomer->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('form.field.fields')" icon="list_alt" tone="info">
        <x-field-schema-editor prefix="fields" :items="$fieldItems" />
    </x-form-group>
</x-modal>
