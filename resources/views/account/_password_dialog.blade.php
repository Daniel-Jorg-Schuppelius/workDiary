@php
    $isDialog = $isDialog ?? true;
@endphp

<x-dialog
    :title="__('Passwort ändern')"
    :eyebrow="__('Konto')"
    icon="🔐"
    tone="warning">
    @if ($mustChange)
        <div class="alert alert-warning mb-4">
            <span>{{ __('Bitte legen Sie ein neues Passwort fest, bevor Sie weiterarbeiten.') }}</span>
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning mb-4">{{ session('warning') }}</div>
    @endif

    <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4" data-entry-form>
        @csrf
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ route('account.password.edit') }}?dialog=1">
        @endif

        @unless ($mustChange)
            <div>
                <label class="label" for="current_password">
                    <span class="label-text">{{ __('Aktuelles Passwort') }}</span>
                </label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" class="input input-bordered w-full" required>
                @error('current_password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
        @endunless

        <div>
            <label class="label" for="password">
                <span class="label-text">{{ __('Neues Passwort') }}</span>
            </label>
            <input type="password" id="password" name="password" autocomplete="new-password" class="input input-bordered w-full" required>
            @error('password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="label" for="password_confirmation">
                <span class="label-text">{{ __('Neues Passwort bestätigen') }}</span>
            </label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="input input-bordered w-full" required>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-sm btn-primary">{{ __('Speichern') }}</button>
            @if ($isDialog)
                <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            @endif
        </div>
    </form>
</x-dialog>
