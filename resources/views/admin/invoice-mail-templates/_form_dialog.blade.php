{{--
  Created on   : Tue Nov 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $template (Model, ggf. ungespeichert), $kinds, $variablesByKind --}}
@php
    $isEdit = $template->exists;
    $currentKind = old('document_kind', $template->document_kind);
@endphp

<x-modal
    :title="$isEdit ? __('Template bearbeiten') : __('Neues Template')"
    :eyebrow="__('Mail-Template')"
    icon="mail"
    tone="primary"
    size="wide"
    :action="$isEdit ? route('admin.invoice-mail-templates.update', $template) : route('admin.invoice-mail-templates.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    {{-- Die Variablen-Doku folgt der gewählten Belegart (reveal, CSP-Build). --}}
    <div class="space-y-4" x-data="reveal(@js($currentKind))">
        <x-input-field name="name" :label="__('Name')" required maxlength="120"
                       :value="old('name', $template->name)" />

        {{-- Feature 128 (MVP-692): Vorlagen gelten je Belegart. --}}
        <x-select-field name="document_kind" :label="__('Belegart')" required x-model="value">
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected($currentKind === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>

        <label class="label cursor-pointer justify-start gap-2">
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $template->is_default))
                   class="checkbox">
            <span class="label-text">{{ __('Als Standard-Template der Belegart verwenden') }}</span>
        </label>

        <x-input-field name="subject" :label="__('Betreff')" required maxlength="255"
                       :value="old('subject', $template->subject)" />

        <x-textarea-field name="body_html" :label="__('HTML-Body')" required rows="10"
                          class="font-mono text-xs"
                          :value="old('body_html', $template->body_html)" />

        <x-textarea-field name="body_text" :label="__('Text-Body (Fallback)')" required rows="8"
                          class="font-mono text-xs"
                          :value="old('body_text', $template->body_text)" />

        {{-- Platzhalter-Doku je Belegart (Feature 128). --}}
        @foreach ($variablesByKind as $kindValue => $kindDoc)
            <details class="text-sm" x-bind:open="is(@js($kindValue))" @if ($currentKind === $kindValue) open @endif>
                <summary class="cursor-pointer text-muted">{{ __('Verfügbare Variablen') }}: {{ $kindDoc['label'] }}</summary>
                <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
                    @foreach ($kindDoc['variables'] as $key => $label)
                        <li><code>&#123;&#123;{{ $key }}&#125;&#125;</code> – {{ $label }}</li>
                    @endforeach
                </ul>
            </details>
        @endforeach
    </div>
</x-modal>
