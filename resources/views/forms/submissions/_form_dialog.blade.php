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
    :form-data="['data-entry-form' => '']"
    :submit-label="__('form.action.submit')">

    <input type="hidden" name="form_template_id" value="{{ $template->sqid }}">
    @if ($subjectKind !== null)
        <input type="hidden" name="subject_kind" value="{{ $subjectKind }}">
        <input type="hidden" name="subject_id" value="{{ $subjectId }}">
    @endif

    @if ($template->description)
        <p class="mb-4 text-sm text-base-content/70">{{ $template->description }}</p>
    @endif

    <x-form-group :legend="$template->name" icon="edit_note" tone="primary" cols="2">
        @foreach ($template->fields ?? [] as $field)
            @php
                $key = (string) $field['key'];
                $name = "values[{$key}]";
                $errKey = "values.{$key}";
                $required = (bool) ($field['required'] ?? false);
                $old = old("values.{$key}");
            @endphp
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
        @endforeach
    </x-form-group>
</x-modal>
