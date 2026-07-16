{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _connect_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: KI-Provider-Verbindung anlegen (Feature 025, MVP-400) --}}
<x-modal
    :title="__('ai.connect.title')"
    icon="smart_toy"
    tone="primary"
    :action="route('admin.ai.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('ai.connect.submit')"
>
    <x-form-group :legend="__('ai.connect.legend')" icon="smart_toy" tone="primary" cols="1">
        <div class="fieldset">
            <label class="fieldset-label" for="ai-name">{{ __('ai.field.name') }}</label>
            <input id="ai-name" type="text" name="name" required maxlength="120"
                   value="{{ old('name') }}" class="input input-bordered w-full">
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ai-family">{{ __('ai.field.family') }}</label>
            <select id="ai-family" name="family" class="select select-bordered w-full">
                @foreach (\App\Enums\Ai\AiFamily::cases() as $family)
                    <option value="{{ $family->value }}" @selected(old('family', 'llm') === $family->value)>{{ $family->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ai-provider">{{ __('ai.field.provider') }}</label>
            <select id="ai-provider" name="provider" class="select select-bordered w-full">
                @foreach (\App\Enums\Ai\AiProviderType::cases() as $provider)
                    @continue($provider === \App\Enums\Ai\AiProviderType::Fake && ! app()->environment('testing', 'local'))
                    <option value="{{ $provider->value }}" @selected(old('provider') === $provider->value)>{{ $provider->label() }}</option>
                @endforeach
            </select>
            <p class="text-xs text-base-content/60">{{ __('ai.field.provider_help') }}</p>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ai-base-url">{{ __('ai.field.base_url') }}</label>
            <input id="ai-base-url" type="url" name="base_url" maxlength="500"
                   value="{{ old('base_url') }}" class="input input-bordered w-full font-mono"
                   placeholder="https://…">
            <p class="text-xs text-base-content/60">{{ __('ai.field.base_url_help') }}</p>
            @error('base_url')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ai-key">{{ __('ai.field.api_key') }}</label>
            <input id="ai-key" type="password" name="api_key" maxlength="2000" autocomplete="new-password"
                   class="input input-bordered w-full font-mono" placeholder="••••••••">
            <p class="text-xs text-base-content/60">{{ __('ai.field.api_key_help') }}</p>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ai-model">{{ __('ai.field.model') }}</label>
            <input id="ai-model" type="text" name="model" maxlength="120"
                   value="{{ old('model') }}" class="input input-bordered w-full font-mono">
            <p class="text-xs text-base-content/60">{{ __('ai.field.model_help') }}</p>
        </div>

        <div class="fieldset">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="checkbox" name="is_local" value="1" class="checkbox checkbox-sm"
                       @checked(old('is_local'))>
                <span class="label-text">{{ __('ai.field.is_local') }}</span>
            </label>
            <p class="text-xs text-base-content/60">{{ __('ai.field.is_local_help') }}</p>
        </div>
    </x-form-group>
</x-modal>
