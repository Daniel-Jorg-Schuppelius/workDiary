@php
    $isDialog = $isDialog ?? true;
@endphp

<x-modal
    :title="__('Profil')"
    :eyebrow="__('Konto')"
    icon="person"
    tone="info"
    :action="route('account.profile.update')"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('account.profile.edit') }}?dialog=1">
    @endif

    <x-form-group :legend="__('Profildaten')" icon="person" tone="info" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="name">{{ __('Name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="input input-bordered w-full" required>
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="email">{{ __('E-Mail') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="input input-bordered w-full" required>
            @error('email')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <x-slot:footerExtra>
        <x-icon-btn icon="tune" size="sm"
                    :href="route('account.settings')"
                    show-label>{{ __('Erweiterte Einstellungen') }}</x-icon-btn>
        <x-icon-btn icon="lock" size="sm"
                    data-entry-modal-trigger
                    :href="route('account.password.edit')"
                    show-label>{{ __('Passwort ändern') }}</x-icon-btn>
    </x-slot:footerExtra>
</x-modal>
