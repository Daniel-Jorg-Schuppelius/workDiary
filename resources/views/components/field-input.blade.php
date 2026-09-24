{{--
  Created on   : Thu Sep 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : field-input.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Eingabe eines Feldes des Feldschema-Bausteins (MVP-866) — ein Markup je
  FieldType für Formulare, Checklisten und Portal. Upload-Felder schreiben in
  files[<key>], Unterschriften in signatures[<key>], alles andere in
  <prefix>[<key>]. Fachtypen (extension) rendern ihre eigene View.
--}}
@props([
    'field',                 // \App\Services\Fields\FieldDefinition
    'value' => null,         // Vorbelegung (old() hat Vorrang)
    'prefix' => 'values',
    'wide' => true,          // Zwei-Spalten-Raster: Textarea/Upload/Unterschrift über beide Spalten
])

@php
    $key = $field->key;
    $type = $field->type;
    $name = $prefix === '' ? $key : "{$prefix}[{$key}]";
    $errKey = $prefix === '' ? $key : "{$prefix}.{$key}";
    $required = $field->required;
    $old = old($errKey, $value);
    $span = $wide ? ' sm:col-span-2' : '';
    $extension = app(\App\Services\Fields\FieldExtensionRegistry::class)->get($field->extension);
    $extensionView = $extension?->inputView();
    $labelText = $field->label . ($field->unit !== null ? " ({$field->unit})" : '') . ($required ? ' *' : '');
@endphp

@if ($extensionView !== null)
    @include($extensionView, ['field' => $field, 'value' => $old, 'name' => $name, 'errKey' => $errKey, 'required' => $required])
@else
    @switch($type)
        @case(\App\Enums\Fields\FieldType::Section)
            <div class="{{ $span ? 'sm:col-span-2' : '' }} pt-2">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-base-content/70">{{ $field->label }}</h3>
                @if ($field->help !== null)
                    <p class="text-xs text-muted">{{ $field->help }}</p>
                @endif
            </div>
            @break
        @case(\App\Enums\Fields\FieldType::Textarea)
            <label class="form-control{{ $span }}">
                <span class="label-text">{{ $labelText }}</span>
                <textarea name="{{ $name }}" rows="3" maxlength="10000" @required($required)
                          class="textarea textarea-bordered w-full @error($errKey) textarea-error @enderror">{{ is_scalar($old) ? $old : '' }}</textarea>
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::Number)
        @case(\App\Enums\Fields\FieldType::Measurement)
            <label class="form-control">
                <span class="label-text">{{ $labelText }}</span>
                <input type="number" step="{{ $field->step ?? 'any' }}" name="{{ $name }}" @required($required)
                       @if($field->min !== null) min="{{ $field->min }}" @endif @if($field->max !== null) max="{{ $field->max }}" @endif
                       class="input input-bordered w-full @error($errKey) input-error @enderror" value="{{ is_scalar($old) ? $old : '' }}">
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::Scale)
            @php $min = (int) ($field->min ?? 1); $max = (int) ($field->max ?? 5); @endphp
            <fieldset class="form-control">
                <legend class="label-text">{{ $labelText }}</legend>
                <div class="join">
                    @for ($i = $min; $i <= $max; $i++)
                        <label class="join-item btn btn-sm btn-outline has-[:checked]:btn-primary">
                            <input type="radio" name="{{ $name }}" value="{{ $i }}" class="sr-only" @checked((string) $old === (string) $i) @required($required)>{{ $i }}
                        </label>
                    @endfor
                </div>
            </fieldset>
            @break
        @case(\App\Enums\Fields\FieldType::Date)
            <label class="form-control">
                <span class="label-text">{{ $labelText }}</span>
                <input type="date" name="{{ $name }}" @required($required)
                       class="input input-bordered w-full @error($errKey) input-error @enderror" value="{{ is_scalar($old) ? $old : '' }}">
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::DateTime)
            <label class="form-control">
                <span class="label-text">{{ $labelText }}</span>
                <input type="datetime-local" name="{{ $name }}" @required($required)
                       class="input input-bordered w-full @error($errKey) input-error @enderror" value="{{ is_scalar($old) ? $old : '' }}">
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::Choice)
            <label class="form-control">
                <span class="label-text">{{ $labelText }}</span>
                <select name="{{ $name }}" @required($required)
                        class="select select-bordered w-full @error($errKey) select-error @enderror">
                    <option value="">—</option>
                    @foreach ($field->options as $option)
                        <option value="{{ $option }}" @selected((string) $old === $option)>{{ $field->optionLabel($option) }}</option>
                    @endforeach
                </select>
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::Multichoice)
            @php $selected = array_map('strval', is_array($old) ? $old : []); @endphp
            <fieldset class="form-control{{ $span }}">
                <legend class="label-text">{{ $labelText }}</legend>
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    @foreach ($field->options as $option)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="{{ $name }}[]" value="{{ $option }}" class="checkbox checkbox-sm" @checked(in_array($option, $selected, true))>
                            <span>{{ $field->optionLabel($option) }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @break
        @case(\App\Enums\Fields\FieldType::Boolean)
            <label class="flex items-center gap-2">
                <input type="hidden" name="{{ $name }}" value="0">
                <input type="checkbox" name="{{ $name }}" value="1" class="checkbox"
                       @checked(filter_var($old ?? false, FILTER_VALIDATE_BOOL)) @required($required)>
                <span>{{ $labelText }}</span>
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::Photo)
        @case(\App\Enums\Fields\FieldType::File)
            <label class="form-control{{ $span }}">
                <span class="label-text">{{ $labelText }}</span>
                <input type="file" name="files[{{ $key }}]" @required($required)
                       accept="{{ $type === \App\Enums\Fields\FieldType::Photo ? 'image/*' : '.jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.log,.zip,.docx,.xlsx' }}"
                       class="file-input file-input-bordered file-input-sm w-full @error($errKey) file-input-error @enderror">
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::Signature)
            @once @push('scripts') @vite('resources/js/signature.js') @endpush @endonce
            {{-- Alpine.data("signaturePad") — CSP-Build-konform; $refs.sigInput trägt das Base64-PNG. --}}
            <div class="form-control{{ $span }}" x-data="signaturePad">
                <span class="label-text">{{ $labelText }}</span>
                <div class="rounded-box border border-base-300 bg-white p-2">
                    <canvas x-ref="canvas" class="block h-32 w-full touch-none"></canvas>
                </div>
                <input type="hidden" name="signatures[{{ $key }}]" x-ref="sigInput">
                <button type="button" class="btn btn-ghost btn-xs mt-1 self-start" @click="clear()">{{ __('fields.action.clear_signature') }}</button>
            </div>
            @break
        @default
            <label class="form-control">
                <span class="label-text">{{ $labelText }}</span>
                <input type="text" name="{{ $name }}" maxlength="500" @required($required)
                       class="input input-bordered w-full @error($errKey) input-error @enderror" value="{{ is_scalar($old) ? $old : '' }}">
            </label>
    @endswitch
@endif
@if ($field->help !== null && $type !== \App\Enums\Fields\FieldType::Section)
    <p class="-mt-1 text-xs text-muted{{ $span }}">{{ $field->help }}</p>
@endif
@error($errKey)
    <p class="-mt-1 text-error text-sm{{ $span }}">{{ $message }}</p>
@enderror
