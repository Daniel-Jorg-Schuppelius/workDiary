{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _connect_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: DomainReselling-Konto per Zugangsdaten anbinden (Feature 083, MVP-385) --}}
<x-modal
    :title="__('domain.connect.title')"
    icon="dns"
    tone="primary"
    :action="route('admin.domain-provider.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('domain.connect.submit')"
>
    <x-form-group :legend="__('domain.connect.legend')" icon="dns" tone="primary" cols="1">
        <div class="fieldset">
            <label class="fieldset-label" for="dr-name">{{ __('domain.field.name') }}</label>
            <input id="dr-name" type="text" name="name" required maxlength="190"
                   value="{{ old('name', 'DomainReselling') }}" class="input input-bordered w-full">
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="dr-env">{{ __('domain.field.environment') }}</label>
            <select id="dr-env" name="environment" class="select select-bordered w-full">
                @foreach (\App\Enums\Domain\DomainProviderEnvironment::cases() as $env)
                    <option value="{{ $env->value }}" @selected(old('environment', 'ote') === $env->value)>{{ $env->label() }}</option>
                @endforeach
            </select>
            <p class="text-xs text-muted">{{ __('domain.field.environment_help') }}</p>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="dr-login">{{ __('domain.field.login') }}</label>
            <input id="dr-login" type="text" name="login" required maxlength="190" autocomplete="off"
                   value="{{ old('login') }}" class="input input-bordered w-full">
            @error('login')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="dr-pass">{{ __('domain.field.password') }}</label>
            <input id="dr-pass" type="password" name="password" required maxlength="512" autocomplete="new-password"
                   class="input input-bordered w-full font-mono" placeholder="••••••••">
            <p class="text-xs text-muted">{{ __('domain.field.password_help') }}</p>
            @error('password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="dr-suser">{{ __('domain.field.default_user') }}</label>
            <input id="dr-suser" type="text" name="default_user" maxlength="190"
                   value="{{ old('default_user') }}" class="input input-bordered w-full">
            <p class="text-xs text-muted">{{ __('domain.field.default_user_help') }}</p>
        </div>
    </x-form-group>
</x-modal>
