{{--
  Created on   : Mon May 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    $isEdit = $isEdit ?? false;
    $isDialog = $isDialog ?? true;
    $action = $isEdit ? route('legacy.users.update', $legacyUser) : route('legacy.users.store');
    $dialogUrl = ($isEdit ? route('legacy.users.edit', $legacyUser) : route('legacy.users.create')) . '?dialog=1';
@endphp

<x-modal
    :title="$isEdit ? __('Mitarbeiter bearbeiten') : __('Mitarbeiter anlegen')"
    :eyebrow="__('Legacy')"
    icon="person"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <div>
        <label for="uname" class="label text-sm font-semibold pb-1">{{ __('Name') }}</label>
        <input id="uname" name="uname" type="text" value="{{ old('uname', $legacyUser?->uname) }}" class="input input-bordered w-full @error('uname') input-error @enderror" required>
        @error('uname')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="userpw" class="label text-sm font-semibold pb-1">
            {{ __('Passwort') }}
            @if ($isEdit)<span class="text-xs font-normal text-base-content/50"> - {{ __('leer lassen um beizubehalten') }}</span>@endif
        </label>
        <input id="userpw" name="userpw" type="password" value="{{ old('userpw') }}" autocomplete="new-password" class="input input-bordered w-full @error('userpw') input-error @enderror" @if (! $isEdit) required @endif>
        @error('userpw')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="email" class="label text-sm font-semibold pb-1">{{ __('E-Mail') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email', $legacyUser?->email) }}" class="input input-bordered w-full @error('email') input-error @enderror">
        @error('email')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
    </div>
</x-modal>
