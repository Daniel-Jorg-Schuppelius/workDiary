{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _memory_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Gedächtnis-Eintrag anlegen (Feature 025, MVP-401).
     Sichtbarkeit der Felder je Typ/Ebene über data-Attribute + app.js-
     freien CSS-Ansatz bewusst simpel: alle Felder sichtbar, Pflicht wird
     serverseitig je Typ geprüft (Glossar → Begriff, Beispiel → Rohtext). --}}
<x-modal
    :title="__('ai.memory.new')"
    icon="psychology"
    tone="primary"
    :action="route('admin.ai.memory.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('ai.memory.submit')"
>
    <x-form-group :legend="__('ai.memory.legend')" icon="psychology" tone="primary" cols="1">
        <div class="fieldset">
            <label class="fieldset-label" for="aim-type">{{ __('ai.field.type') }}</label>
            <select id="aim-type" name="entry_type" class="select select-bordered w-full">
                @foreach (\App\Enums\Ai\AiMemoryEntryType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('entry_type', 'glossary') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <p class="text-xs text-base-content/60">{{ __('ai.memory.type_help') }}</p>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="aim-scope">{{ __('ai.field.scope') }}</label>
            <select id="aim-scope" name="scope" class="select select-bordered w-full">
                <option value="organization" @selected(old('scope', 'organization') === 'organization')>{{ __('ai.field.scope_organization') }}</option>
                <option value="customer" @selected(old('scope') === 'customer')>{{ __('ai.field.scope_customer') }}</option>
                <option value="capability" @selected(old('scope') === 'capability')>{{ __('ai.field.scope_capability') }}</option>
            </select>
            <p class="text-xs text-base-content/60">{{ __('ai.memory.scope_help') }}</p>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="aim-customer">{{ __('ai.field.customer') }}</label>
            <select id="aim-customer" name="customer_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->sqid }}" @selected((string) old('customer_id') === $customer->sqid)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="aim-capability">{{ __('ai.field.capability') }}</label>
            <select id="aim-capability" name="capability" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($capabilities as $capability)
                    <option value="{{ $capability->key }}" @selected(old('capability') === $capability->key)>{{ $capability->key }}</option>
                @endforeach
            </select>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="aim-term">{{ __('ai.field.term') }}</label>
            <input id="aim-term" type="text" name="term" maxlength="120"
                   value="{{ old('term') }}" class="input input-bordered w-full font-mono"
                   placeholder="{{ __('ai.memory.term_placeholder') }}">
            @error('term')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="aim-source">{{ __('ai.field.source_text') }}</label>
            <textarea id="aim-source" name="source_text" maxlength="2000" rows="2"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('ai.memory.source_placeholder') }}">{{ old('source_text') }}</textarea>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="aim-content">{{ __('ai.field.content') }}</label>
            <textarea id="aim-content" name="content" required maxlength="2000" rows="3"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('ai.memory.content_placeholder') }}">{{ old('content') }}</textarea>
            @error('content')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <span class="fieldset-label">{{ __('ai.field.translations') }}</span>
            <p class="text-xs text-base-content/60">{{ __('ai.memory.translations_help') }}</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach (['en', 'es', 'fr', 'it'] as $lang)
                    <input type="text" name="translation_{{ $lang }}" maxlength="300"
                           value="{{ old('translation_' . $lang) }}"
                           class="input input-bordered input-sm w-full" placeholder="{{ strtoupper($lang) }}">
                @endforeach
            </div>
        </div>
    </x-form-group>
</x-modal>
