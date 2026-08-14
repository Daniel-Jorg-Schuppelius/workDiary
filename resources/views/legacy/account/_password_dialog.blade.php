{{--
  Created on   : Mon May 11 2026
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
    :eyebrow="__('Legacy')"
    icon="lock"
    tone="warning"
    :action="route('legacy.account.password.update')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Ändern')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('legacy.account.password.edit') }}?dialog=1">
    @endif

    <div>
        <label for="current_password" class="label text-sm font-semibold pb-1">{{ __('Altes Passwort') }}</label>
        <input id="current_password" name="current_password" type="password" class="input input-bordered w-full @error('current_password') input-error @enderror" required>
        @error('current_password')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="password" class="label text-sm font-semibold pb-1">{{ __('Neues Passwort') }}</label>
        <input id="password" name="password" type="password" class="input input-bordered w-full @error('password') input-error @enderror" required>
        @error('password')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="password_confirmation" class="label text-sm font-semibold pb-1">{{ __('Neues Passwort (Wiederholung)') }}</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="input input-bordered w-full" required>
    </div>
</x-modal>
