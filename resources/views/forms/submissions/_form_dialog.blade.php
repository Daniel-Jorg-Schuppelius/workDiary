{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Ausfüll-Dialog (Feature 032): dynamisch gerenderte Felder aus der
  AKTIVEN Vorlagen-Definition (in #entry-modal geladen).
  Variablen: $template (FormTemplate), $subjectKind (?string), $subjectId (?string)
--}}

<x-modal
    :title="__('form.action.fill') . ': ' . $template->name"
    :eyebrow="__('form.title.submissions')"
    icon="edit_note"
    tone="primary"
    size="lg"
    :action="route('form-submissions.store')"
    method="POST"
    :form-data="['data-entry-form' => '', 'enctype' => 'multipart/form-data']"
    :submit-label="__('form.action.submit')">

    <input type="hidden" name="form_template_id" value="{{ $template->sqid }}">
    @if ($subjectKind !== null)
        <input type="hidden" name="subject_kind" value="{{ $subjectKind }}">
        <input type="hidden" name="subject_id" value="{{ $subjectId }}">
    @endif

    @if ($template->description)
        <p class="mb-4 text-sm text-base-content/70">{{ $template->description }}</p>
    @endif

    @php
        // Bedingungslogik (Rang 33): clientseitige Sichtbarkeit spiegelt
        // FormFieldDefinition::isVisible. Nur Felder MIT Bedingung werden
        // reaktiv umschaltet; die Quelle-Werte trackt der Wrapper generisch.
        $conditions = collect($template->fields ?? [])
            ->filter(fn($f) => filled($f['visible_if']['field'] ?? null))
            ->mapWithKeys(fn($f) => [(string) $f['key'] => $f['visible_if']])
            ->all();
        $initialVals = collect($template->fields ?? [])
            ->mapWithKeys(function ($f) {
                $k = (string) $f['key'];
                $v = old("values.{$k}");
                if ($v === null) {
                    $v = ($f['type'] ?? '') === \App\Enums\Form\FormFieldType::Checkbox->value ? '0' : '';
                }

                return [$k => (string) $v];
            })->all();
    @endphp
    <x-form-group :legend="$template->name" icon="edit_note" tone="primary" cols="2">
        <div class="contents"
             x-data="formFill(@js($conditions), @js($initialVals))"
             @input.capture="track($event)" @change.capture="track($event)">
        @foreach ($template->fields ?? [] as $field)
            @php
                $key = (string) $field['key'];
                $name = "values[{$key}]";
                $errKey = "values.{$key}";
                $required = (bool) ($field['required'] ?? false);
                $old = old("values.{$key}");
                $hasCondition = filled($field['visible_if']['field'] ?? null);
            @endphp
            @if ($hasCondition)
                <div class="contents" x-show="visible('{{ $key }}')" x-cloak>
            @endif
            @switch($field['type'])
                @case(\App\Enums\Form\FormFieldType::Textarea->value)
                    <label class="form-control sm:col-span-2">
                        <span class="label-text">{{ $field['label'] }} @if($required)*@endif</span>
                        <textarea name="{{ $name }}" rows="3" maxlength="10000" @required($required)
                                  class="textarea textarea-bordered w-full @error($errKey) textarea-error @enderror">{{ $old }}</textarea>
                    </label>
                    @break
                @case(\App\Enums\Form\FormFieldType::Number->value)
                    <label class="form-control">
                        <span class="label-text">
                            {{ $field['label'] }}@if(filled($field['unit'] ?? null)) ({{ $field['unit'] }})@endif @if($required)*@endif
                        </span>
                        <input type="number" step="any" name="{{ $name }}" @required($required)
                               class="input input-bordered w-full @error($errKey) input-error @enderror" value="{{ $old }}">
                    </label>
                    @break
                @case(\App\Enums\Form\FormFieldType::Date->value)
                    <label class="form-control">
                        <span class="label-text">{{ $field['label'] }} @if($required)*@endif</span>
                        <input type="date" name="{{ $name }}" @required($required)
                               class="input input-bordered w-full @error($errKey) input-error @enderror" value="{{ $old }}">
                    </label>
                    @break
                @case(\App\Enums\Form\FormFieldType::Select->value)
                    <label class="form-control">
                        <span class="label-text">{{ $field['label'] }} @if($required)*@endif</span>
                        <select name="{{ $name }}" @required($required)
                                class="select select-bordered w-full @error($errKey) select-error @enderror">
                            <option value="">—</option>
                            @foreach ((array) ($field['options'] ?? []) as $option)
                                <option value="{{ $option }}" @selected($old === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                    @break
                @case(\App\Enums\Form\FormFieldType::Checkbox->value)
                    <label class="flex items-center gap-2">
                        <input type="hidden" name="{{ $name }}" value="0">
                        <input type="checkbox" name="{{ $name }}" value="1" class="checkbox"
                               @checked((bool) $old) @required($required)>
                        <span>{{ $field['label'] }} @if($required)*@endif</span>
                    </label>
                    @break
                @case(\App\Enums\Form\FormFieldType::Photo->value)
                @case(\App\Enums\Form\FormFieldType::File->value)
                    {{-- Foto/Datei (Rang 32): eigener Upload-Kanal files[<key>]. --}}
                    <label class="form-control sm:col-span-2">
                        <span class="label-text">{{ $field['label'] }} @if($required)*@endif</span>
                        <input type="file" name="files[{{ $key }}]" @required($required)
                               accept="{{ $field['type'] === \App\Enums\Form\FormFieldType::Photo->value ? 'image/*' : '.jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.log,.zip,.docx,.xlsx' }}"
                               class="file-input file-input-bordered file-input-sm w-full @error($errKey) file-input-error @enderror">
                    </label>
                    @break
                @case(\App\Enums\Form\FormFieldType::Signature->value)
                    {{-- Unterschrift (Rang 32): Signatur-Pad → Base64-PNG in signatures[<key>]. --}}
                    @once @push('scripts') @vite('resources/js/signature.js') @endpush @endonce
                    <div class="form-control sm:col-span-2"
                         x-data="{ pad: null, init() { this.pad = new window.SignaturePad(this.$refs.canvas); this.pad.addEventListener('endStroke', () => { this.$refs.sig.value = this.pad.toDataURL('image/png'); }); }, clear() { this.pad && this.pad.clear(); this.$refs.sig.value = ''; } }">
                        <span class="label-text">{{ $field['label'] }} @if($required)*@endif</span>
                        <div class="rounded-box border border-base-300 bg-white p-2">
                            <canvas x-ref="canvas" class="block h-32 w-full touch-none"></canvas>
                        </div>
                        <input type="hidden" name="signatures[{{ $key }}]" x-ref="sig">
                        <button type="button" class="btn btn-ghost btn-xs mt-1 self-start" @click="clear()">{{ __('form.action.clear_signature') }}</button>
                    </div>
                    @break
                @default
                    <label class="form-control">
                        <span class="label-text">{{ $field['label'] }} @if($required)*@endif</span>
                        <input type="text" name="{{ $name }}" maxlength="500" @required($required)
                               class="input input-bordered w-full @error($errKey) input-error @enderror" value="{{ $old }}">
                    </label>
            @endswitch
            @if (filled($field['help'] ?? null))
                <p class="-mt-1 text-xs text-base-content/60 sm:col-span-2">{{ $field['help'] }}</p>
            @endif
            @error($errKey)
                <p class="-mt-1 text-error text-sm sm:col-span-2">{{ $message }}</p>
            @enderror
            @if ($hasCondition)
                </div>
            @endif
        @endforeach
        </div>
    </x-form-group>
</x-modal>
