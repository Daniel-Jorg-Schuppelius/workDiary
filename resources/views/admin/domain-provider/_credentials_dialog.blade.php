{{--
  Created on   : Sun Nov 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _credentials_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Zugangsdaten erneuern bzw. getrennte Verbindung wieder verbinden (MVP-1492) --}}
<x-modal
    :title="__('domain.credentials.title')"
    :eyebrow="$connection->name"
    icon="key"
    tone="primary"
    :action="route('admin.domain-provider.rotate', $connection)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('domain.credentials.submit')"
>
    <x-form-group :legend="__('domain.credentials.legend')" icon="key" tone="primary" cols="1">
        <p class="text-sm text-muted">{{ __('domain.credentials.help') }}</p>

        <div class="fieldset">
            <label class="fieldset-label" for="drc-login">{{ __('domain.field.login') }}</label>
            <input id="drc-login" type="text" name="login" maxlength="190" autocomplete="off"
                   value="{{ old('login') }}" class="input input-bordered w-full" placeholder="{{ $connection->login }}">
            <p class="text-xs text-muted">{{ __('domain.credentials.keep_help') }}</p>
            @error('login')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="drc-pass">{{ __('domain.credentials.password') }}</label>
            <input id="drc-pass" type="password" name="password" required maxlength="512" autocomplete="new-password"
                   class="input input-bordered w-full font-mono" placeholder="••••••••">
            <p class="text-xs text-muted">{{ __('domain.field.password_help') }}</p>
            @error('password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="drc-suser">{{ __('domain.field.default_user') }}</label>
            <input id="drc-suser" type="text" name="default_user" maxlength="190"
                   value="{{ old('default_user') }}" class="input input-bordered w-full" placeholder="{{ $connection->default_user }}">
            <p class="text-xs text-muted">{{ __('domain.credentials.keep_help') }}</p>
        </div>
    </x-form-group>
</x-modal>
