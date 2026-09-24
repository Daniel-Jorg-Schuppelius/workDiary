{{--
  Created on   : Thu Sep 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Schema-Dialog eines Trägers (MVP-868). Variablen: $alias, $label,
  $definition (CustomFieldDefinition|null), $types (list<FieldType>)
--}}
@php
    $items = old('fields', $definition !== null
        ? collect($definition->schema->all())->map(fn ($f) => [
            'label' => $f->label,
            'type' => $f->type->value,
            'required' => $f->required,
            'listed' => $f->listed,
            'options' => implode(', ', $f->options),
            'help' => (string) $f->help,
            'unit' => (string) $f->unit,
            'min' => $f->min === null ? '' : (string) $f->min,
            'max' => $f->max === null ? '' : (string) $f->max,
            'visible_if' => [
                'field' => $f->visibleIf !== null ? (string) ($definition->schema->get($f->visibleIf['field'])?->label ?? $f->visibleIf['field']) : '',
                'op' => $f->visibleIf['op'] ?? 'eq',
                'value' => $f->visibleIf['value'] ?? '',
            ],
        ])->values()->all()
        : null);
@endphp
<x-modal
    :title="__('fields.custom.edit') . ': ' . $label"
    :eyebrow="__('fields.custom.title')"
    icon="dashboard_customize"
    tone="primary"
    size="lg"
    :action="route('admin.custom-fields.update', $alias)"
    method="PUT"
    :submit-label="__('fields.custom.save')">

    <x-form-group :legend="__('fields.custom.fields')" icon="list_alt" tone="info">
        <label class="flex items-center gap-2 sm:col-span-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-sm" @checked(old('is_active', $definition?->is_active ?? true))>
            <span class="text-sm">{{ __('fields.custom.active') }}</span>
        </label>
        <x-field-schema-editor prefix="fields" :items="$items" :types="$types" listable />
    </x-form-group>
</x-modal>
