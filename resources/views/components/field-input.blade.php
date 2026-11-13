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

  Lange Formulare (Feature 203, MVP-1651): Auswahl und Mehrfachauswahl bis
  FieldLayout::CHOICE_BUTTONS_MAX Möglichkeiten als Knöpfe (fieldset/legend,
  Pfeiltasten bzw. Leertaste), darüber Auswahlliste; Breite nach Typ
  (FieldLayout::fullWidth); Eingaben mit Fläche und Rahmen aus den
  Theme-Tokens (.wd-field-control); Hilfe und Fehler über aria-describedby.
  Optional ein Punkt offen/beantwortet vor der Frage (marker, nur innerhalb
  von <x-field-form>) und die Ergänzung notes[<key>] (notes).
--}}
@props([
    'field',                 // \App\Services\Fields\FieldDefinition
    'value' => null,         // Vorbelegung (old() hat Vorrang)
    'prefix' => 'values',
    'wide' => true,          // Zwei-Spalten-Raster: breite Felder über beide Spalten
    'marker' => null,        // null = kein Punkt; bool = Punkt beantwortet/offen (nur im Formular „field-form“)
    'notes' => false,        // Ergänzungsfeld anbieten (Träger speichert notes[<key>])
    'note' => null,          // gespeicherte Ergänzung
    'idPrefix' => null,      // eindeutiger Präfix der IDs (mehrere Formulare auf einer Seite)
])

@php
    $key = $field->key;
    $type = $field->type;
    $name = $prefix === '' ? $key : "{$prefix}[{$key}]";
    $errKey = $prefix === '' ? $key : "{$prefix}.{$key}";
    $notesName = $prefix === 'values' || $prefix === '' ? 'notes' : $prefix . '_notes';
    $required = $field->required;
    $old = old($errKey, $value);
    $span = $wide && \App\Services\Fields\FieldLayout::fullWidth($field) ? ' sm:col-span-2' : '';
    $extension = app(\App\Services\Fields\FieldExtensionRegistry::class)->get($field->extension);
    $extensionView = $extension?->inputView();
    $labelText = $field->label . ($field->unit !== null ? " ({$field->unit})" : '') . ($required ? ' *' : '');
    $id = ($idPrefix ?? ('fld-' . (preg_replace('/[^A-Za-z0-9_-]/', '', $prefix) ?: 'x'))) . '-' . $key;
    // Upload-Felder melden Fehler auch unter files.<key> (Dateiregeln des Kanals).
    $errorMessage = $errors->first($errKey) ?: ($type->isUpload() ? $errors->first('files.' . $key) : '');
    $hasError = $errorMessage !== '';
    $helpId = $field->help !== null && $type->hasValue() ? $id . '-help' : null;
    $errorId = $hasError ? $id . '-error' : null;
    $describedBy = trim(($helpId ?? '') . ' ' . ($errorId ?? ''));
    $ariaAttrs = new \Illuminate\View\ComponentAttributeBag(array_filter([
        'aria-describedby' => $describedBy !== '' ? $describedBy : null,
        'aria-invalid' => $hasError ? 'true' : null,
        'aria-required' => $required ? 'true' : null,
    ], static fn (?string $v): bool => $v !== null));
    // Punkt offen/beantwortet vor der Frage: Form (Ring/Scheibe mit Haken), nicht nur Farbe;
    // nachgeführt von Alpine „fieldForm“ (nur innerhalb von <x-field-form>).
    $dot = $marker === null ? '' : new \Illuminate\Support\HtmlString(
        '<span class="wd-field-dot' . ($marker ? ' wd-field-dot--done' : '') . '" data-field-dot="' . e($key) . '"'
        . ' :class="dotClass(\'' . e($key) . '\')" aria-hidden="true"></span>'
    );
    $asButtons = \App\Services\Fields\FieldLayout::choiceAsButtons($field);
    $noteValue = old(($notesName) . '.' . $key, $note);
    $showNote = $notes && $type->hasValue() && ($field->allowsNote || (is_string($noteValue) && $noteValue !== ''));
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
                <span class="label-text">{{ $dot }}<span>{{ $labelText }}</span></span>
                <textarea name="{{ $name }}" id="{{ $id }}" rows="3" maxlength="10000" @required($required) {{ $ariaAttrs }}
                          class="textarea textarea-bordered wd-field-control wd-field-control--grow w-full {{ $hasError ? 'textarea-error' : '' }}">{{ is_scalar($old) ? $old : '' }}</textarea>
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::Number)
        @case(\App\Enums\Fields\FieldType::Measurement)
            <label class="form-control{{ $span }}">
                <span class="label-text">{{ $dot }}<span>{{ $labelText }}</span></span>
                <input type="number" step="{{ $field->step ?? 'any' }}" name="{{ $name }}" id="{{ $id }}" @required($required) {{ $ariaAttrs }}
                       @if($field->min !== null) min="{{ $field->min }}" @endif @if($field->max !== null) max="{{ $field->max }}" @endif
                       class="input input-bordered wd-field-control w-full {{ $hasError ? 'input-error' : '' }}" value="{{ is_scalar($old) ? $old : '' }}">
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::Scale)
            @php $min = (int) ($field->min ?? 1); $max = (int) ($field->max ?? 5); @endphp
            <fieldset id="{{ $id }}" class="form-control wd-choice{{ $span }}" @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
                <legend class="label-text">{{ $dot }}<span>{{ $labelText }}</span></legend>
                <div class="wd-choice__options">
                    @for ($i = $min; $i <= $max; $i++)
                        <label class="wd-choice__option wd-choice__option--compact">
                            <input type="radio" name="{{ $name }}" value="{{ $i }}" class="wd-choice__input" @checked((string) $old === (string) $i) @required($required)>
                            <span class="wd-choice__text">{{ $i }}</span>
                        </label>
                    @endfor
                </div>
            </fieldset>
            @break
        @case(\App\Enums\Fields\FieldType::Date)
            <label class="form-control{{ $span }}">
                <span class="label-text">{{ $dot }}<span>{{ $labelText }}</span></span>
                <input type="date" name="{{ $name }}" id="{{ $id }}" @required($required) {{ $ariaAttrs }}
                       class="input input-bordered wd-field-control w-full {{ $hasError ? 'input-error' : '' }}" value="{{ is_scalar($old) ? $old : '' }}">
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::DateTime)
            <label class="form-control{{ $span }}">
                <span class="label-text">{{ $dot }}<span>{{ $labelText }}</span></span>
                <input type="datetime-local" name="{{ $name }}" id="{{ $id }}" @required($required) {{ $ariaAttrs }}
                       class="input input-bordered wd-field-control w-full {{ $hasError ? 'input-error' : '' }}" value="{{ is_scalar($old) ? $old : '' }}">
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::Choice)
            @if ($asButtons)
                <fieldset id="{{ $id }}" class="form-control wd-choice{{ $span }}" @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
                    <legend class="label-text">{{ $dot }}<span>{{ $labelText }}</span></legend>
                    <div class="wd-choice__options">
                        @foreach ($field->options as $option)
                            <label class="wd-choice__option">
                                <input type="radio" name="{{ $name }}" value="{{ $option }}" class="wd-choice__input" @checked((string) $old === $option) @required($required)>
                                <x-icon name="check" class="wd-choice__check" />
                                <span class="wd-choice__text">{{ $field->optionLabel($option) }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @else
                <label class="form-control{{ $span }}">
                    <span class="label-text">{{ $dot }}<span>{{ $labelText }}</span></span>
                    <select name="{{ $name }}" id="{{ $id }}" @required($required) {{ $ariaAttrs }}
                            class="select select-bordered wd-field-control w-full {{ $hasError ? 'select-error' : '' }}">
                        <option value="">—</option>
                        @foreach ($field->options as $option)
                            <option value="{{ $option }}" @selected((string) $old === $option)>{{ $field->optionLabel($option) }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @break
        @case(\App\Enums\Fields\FieldType::Multichoice)
            @php $selected = array_map('strval', is_array($old) ? $old : []); @endphp
            <fieldset id="{{ $id }}" class="form-control wd-choice{{ $span }}" @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
                <legend class="label-text">{{ $dot }}<span>{{ $labelText }}</span></legend>
                <div @class(['wd-choice__options' => $asButtons, 'wd-choice__list' => ! $asButtons])>
                    @foreach ($field->options as $option)
                        @if ($asButtons)
                            <label class="wd-choice__option">
                                <input type="checkbox" name="{{ $name }}[]" value="{{ $option }}" class="wd-choice__input" @checked(in_array($option, $selected, true))>
                                <x-icon name="check" class="wd-choice__check" />
                                <span class="wd-choice__text">{{ $field->optionLabel($option) }}</span>
                            </label>
                        @else
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="{{ $name }}[]" value="{{ $option }}" class="checkbox checkbox-sm" @checked(in_array($option, $selected, true))>
                                <span>{{ $field->optionLabel($option) }}</span>
                            </label>
                        @endif
                    @endforeach
                </div>
            </fieldset>
            @break
        @case(\App\Enums\Fields\FieldType::Boolean)
            <label class="flex items-center gap-2{{ $span }}">
                <input type="hidden" name="{{ $name }}" value="0">
                <input type="checkbox" name="{{ $name }}" value="1" id="{{ $id }}" class="checkbox wd-field-control" {{ $ariaAttrs }}
                       @checked(filter_var($old ?? false, FILTER_VALIDATE_BOOL)) @required($required)>
                {{ $dot }}<span>{{ $labelText }}</span>
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::Photo)
        @case(\App\Enums\Fields\FieldType::File)
            <label class="form-control{{ $span }}">
                <span class="label-text">{{ $dot }}<span>{{ $labelText }}</span></span>
                <input type="file" name="files[{{ $key }}]" id="{{ $id }}" @required($required) {{ $ariaAttrs }}
                       accept="{{ $type === \App\Enums\Fields\FieldType::Photo ? 'image/*' : '.jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.log,.zip,.docx,.xlsx' }}"
                       class="file-input file-input-bordered file-input-sm wd-field-control w-full {{ $hasError ? 'file-input-error' : '' }}">
            </label>
            @break
        @case(\App\Enums\Fields\FieldType::Signature)
            @once @push('scripts') @vite('resources/js/signature.js') @endpush @endonce
            {{-- Alpine.data("signaturePad") — CSP-Build-konform; $refs.sigInput trägt das Base64-PNG. --}}
            <div class="form-control{{ $span }}" x-data="signaturePad">
                <span class="label-text">{{ $dot }}<span>{{ $labelText }}</span></span>
                <div class="rounded-box border border-base-300 bg-white p-2">
                    <canvas x-ref="canvas" class="block h-32 w-full touch-none"></canvas>
                </div>
                <input type="hidden" name="signatures[{{ $key }}]" x-ref="sigInput">
                <button type="button" class="btn btn-ghost btn-xs mt-1 self-start" @click="clear()">{{ __('fields.action.clear_signature') }}</button>
            </div>
            @break
        @default
            <label class="form-control{{ $span }}">
                <span class="label-text">{{ $dot }}<span>{{ $labelText }}</span></span>
                <input type="text" name="{{ $name }}" id="{{ $id }}" maxlength="500" @required($required) {{ $ariaAttrs }}
                       class="input input-bordered wd-field-control w-full {{ $hasError ? 'input-error' : '' }}" value="{{ is_scalar($old) ? $old : '' }}">
            </label>
    @endswitch
@endif
@if ($helpId !== null)
    <p id="{{ $helpId }}" class="-mt-1 text-xs text-muted{{ $span }}">{{ $field->help }}</p>
@endif
@if ($showNote)
    <label class="form-control wd-field-note{{ $span }}">
        <span class="label-text text-xs text-muted">{{ __('fields.note.label') }}</span>
        <input type="text" name="{{ $notesName }}[{{ $key }}]" id="{{ $id }}-note" maxlength="2000"
               class="input input-sm input-bordered wd-field-control w-full" value="{{ is_scalar($noteValue) ? $noteValue : '' }}">
    </label>
@endif
@if ($hasError)
    <p id="{{ $id }}-error" class="-mt-1 text-error text-sm{{ $span }}">{{ $errorMessage }}</p>
@endif
