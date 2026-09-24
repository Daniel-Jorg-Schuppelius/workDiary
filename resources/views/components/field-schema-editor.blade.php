{{--
  Created on   : Thu Sep 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : field-schema-editor.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zeilen-Editor für ein Feldschema (MVP-866/868): Bezeichnung, Typ, Pflicht,
  Optionen, Einheit, Wertebereich, Hilfe, Sichtbarkeitsbedingung. Sendet
  <prefix>[i][label|type|required|listed|options|help|unit|min|max|visible_if] —
  serverseitig normalisiert FieldSchema::fromRows(). Alpine "repeater".
--}}
@props([
    'prefix' => 'fields',
    'items' => null,                 // vorbelegte Zeilen (Array), null = eine leere Zeile
    'types' => null,                 // erlaubte FieldType-Fälle, null = alle
    'conditions' => true,            // Sichtbarkeitsbedingungen anbieten
    'listable' => false,             // Option „In der Liste zeigen" (eigene Felder, Welle 4.6)
])

@php
    $types ??= \App\Enums\Fields\FieldType::cases();
    $conditionTemplate = ['field' => '', 'op' => 'eq', 'value' => ''];
    $template = ['label' => '', 'type' => \App\Enums\Fields\FieldType::Text->value, 'required' => false, 'options' => '', 'help' => '', 'unit' => '', 'min' => '', 'max' => '', 'listed' => false, 'visible_if' => $conditionTemplate];
    $rows = $items ?? [$template];
@endphp

@error($prefix)
    <p class="text-error text-sm sm:col-span-2">{{ $message }}</p>
@enderror
<div x-data="repeater"
     data-prefix="{{ $prefix }}"
     data-items="{{ json_encode($rows) }}"
     data-template="{{ json_encode($template) }}"
     class="space-y-2 sm:col-span-2">
    <template x-for="(it, i) in items" :key="i">
        <div class="space-y-2 rounded-box border border-base-300 bg-base-200/40 p-3">
            <div @class(["grid grid-cols-1 items-end gap-2", "sm:grid-cols-[1fr_auto_auto_auto_auto]" => $listable, "sm:grid-cols-[1fr_auto_auto_auto]" => ! $listable])>
                <div class="fieldset">
                    <label :for="fieldName(i, 'label')" class="fieldset-label">{{ __('form.field.field_label') }}</label>
                    <input type="text" maxlength="160"
                           :id="fieldName(i, 'label')" :name="fieldName(i, 'label')" x-model="it.label"
                           class="input input-sm input-bordered w-full">
                </div>
                <div class="fieldset">
                    <label :for="fieldName(i, 'type')" class="fieldset-label">{{ __('form.field.field_type') }}</label>
                    <select :id="fieldName(i, 'type')" :name="fieldName(i, 'type')" x-model="it.type"
                            class="select select-sm select-bordered">
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="label cursor-pointer gap-2 pb-1">
                    <input type="checkbox" class="checkbox checkbox-sm"
                           :name="fieldName(i, 'required')" x-model="it.required">
                    <span class="label-text text-xs">{{ __('form.field.field_required') }}</span>
                </label>
                @if ($listable)
                    <label class="label cursor-pointer gap-2 pb-1">
                        <input type="checkbox" class="checkbox checkbox-sm"
                               :name="fieldName(i, 'listed')" x-model="it.listed">
                        <span class="label-text text-xs">{{ __('fields.custom.listed') }}</span>
                    </label>
                @endif
                <x-icon-btn icon="close" tone="error" size="xs" type="button"
                            :label="__('form.action.remove_field')" @click="remove(i)" />
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div class="fieldset" x-show="['choice', 'multichoice'].includes(it.type)">
                    <label :for="fieldName(i, 'options')" class="fieldset-label">{{ __('form.field.field_options') }}</label>
                    <input type="text" maxlength="2000"
                           placeholder="{{ __('form.hint.options') }}"
                           :id="fieldName(i, 'options')" :name="fieldName(i, 'options')" x-model="it.options"
                           class="input input-sm input-bordered w-full">
                </div>
                <div class="fieldset" x-show="['number', 'measurement'].includes(it.type)">
                    <label :for="fieldName(i, 'unit')" class="fieldset-label">{{ __('form.field.field_unit') }}</label>
                    <input type="text" maxlength="20"
                           placeholder="{{ __('form.hint.unit') }}"
                           :id="fieldName(i, 'unit')" :name="fieldName(i, 'unit')" x-model="it.unit"
                           class="input input-sm input-bordered w-full">
                </div>
                <div class="fieldset" x-show="['number', 'scale', 'measurement'].includes(it.type)">
                    <label :for="fieldName(i, 'min')" class="fieldset-label">{{ __('form.field.field_range') }}</label>
                    <div class="join w-full">
                        <input type="number" step="any" :id="fieldName(i, 'min')" :name="fieldName(i, 'min')" x-model="it.min"
                               placeholder="{{ __('form.field.field_min') }}" class="input input-sm input-bordered join-item w-full">
                        <input type="number" step="any" :name="fieldName(i, 'max')" x-model="it.max"
                               aria-label="{{ __('form.field.field_max') }}" placeholder="{{ __('form.field.field_max') }}" class="input input-sm input-bordered join-item w-full">
                    </div>
                </div>
                <div class="fieldset">
                    <label :for="fieldName(i, 'help')" class="fieldset-label">{{ __('form.field.field_help') }}</label>
                    <input type="text" maxlength="500"
                           :id="fieldName(i, 'help')" :name="fieldName(i, 'help')" x-model="it.help"
                           class="input input-sm input-bordered w-full">
                </div>
            </div>
            @if ($conditions)
                {{-- Bedingungslogik (Rang 33): Feld nur zeigen, wenn ein anderes
                     Feld einen Wert/Zustand hat. Referenz über Label (Key entsteht
                     serverseitig). --}}
                <div class="grid grid-cols-1 items-end gap-2 rounded-box bg-base-100/60 p-2 sm:grid-cols-[auto_1fr_auto_1fr]">
                    <label :for="fieldName(i, 'visible_if][field')" class="fieldset-label text-xs">{{ __('form.condition.legend') }}</label>
                    <select :id="fieldName(i, 'visible_if][field')" :name="fieldName(i, 'visible_if][field')" x-model="it.visible_if.field"
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
                    <input aria-label="{{ __('form.condition.value_placeholder') }}" type="text" maxlength="500"
                           :name="fieldName(i, 'visible_if][value')" x-model="it.visible_if.value"
                           x-show="it.visible_if.field && it.visible_if.op !== 'filled'"
                           :placeholder="'{{ __('form.condition.value_placeholder') }}'"
                           class="input input-xs input-bordered w-full">
                </div>
            @endif
        </div>
    </template>

    <x-icon-btn icon="add" tone="ghost" size="sm" type="button" show-label @click="add()">
        {{ __('form.action.add_field') }}
    </x-icon-btn>
</div>
