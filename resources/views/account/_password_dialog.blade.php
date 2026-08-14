{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _password_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    $isDialog = $isDialog ?? true;
@endphp

<x-modal
    :title="__('Passwort ändern')"
    :eyebrow="__('Konto')"
    icon="lock"
    tone="warning"
    :action="route('account.password.update')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    @if ($mustChange)
        <div class="alert alert-warning mb-4">
            <span>{{ __('Bitte legen Sie ein neues Passwort fest, bevor Sie weiterarbeiten.') }}</span>
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning mb-4">{{ session('warning') }}</div>
    @endif

    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('account.password.edit') }}?dialog=1">
    @endif

    @unless ($mustChange)
        <x-form-group :legend="__('Aktuelles Passwort')" icon="lock_open" tone="info">
            <div class="fieldset">
                <label class="fieldset-label" for="current_password">{{ __('Aktuelles Passwort') }}</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" class="input input-bordered w-full" required>
                @error('current_password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
        </x-form-group>
    @endunless

    <x-form-group :legend="__('Neues Passwort')" icon="lock" tone="warning" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="password">{{ __('Neues Passwort') }}</label>
            <input type="password" id="password" name="password" autocomplete="new-password" class="input input-bordered w-full" required>
            @error('password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="password_confirmation">{{ __('Bestätigen') }}</label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="input input-bordered w-full" required>
        </div>
    </x-form-group>
</x-modal>
