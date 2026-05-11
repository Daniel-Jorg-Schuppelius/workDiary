@php
    $isDialog = $isDialog ?? true;
@endphp

<x-dialog
    :title="__('Passwort ändern')"
    :eyebrow="__('Legacy')"
    icon="🔐"
    tone="warning">
    <form method="POST" action="{{ route('legacy.account.password.update') }}" class="space-y-4" data-entry-form>
        @csrf
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ route('legacy.account.password.edit') }}?dialog=1">
        @endif

        <div>
            <label for="current_password" class="label text-sm font-semibold pb-1">{{ __('Altes Passwort') }}</label>
            <input id="current_password" name="current_password" type="password" class="input input-bordered input-sm w-full @error('current_password') input-error @enderror" required>
            @error('current_password')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password" class="label text-sm font-semibold pb-1">{{ __('Neues Passwort') }}</label>
            <input id="password" name="password" type="password" class="input input-bordered input-sm w-full @error('password') input-error @enderror" required>
            @error('password')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="label text-sm font-semibold pb-1">{{ __('Neues Passwort (Wiederholung)') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="input input-bordered input-sm w-full" required>
        </div>

        <div class="flex gap-2 pt-1 justify-end">
            @if ($isDialog)
                <button type="button" class="btn btn-ghost btn-sm" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            @endif
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Ändern') }}</button>
        </div>
    </form>
</x-dialog>
