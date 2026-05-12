@php
    $isDialog = $isDialog ?? true;
@endphp

<x-dialog
    :title="__('Profil')"
    :eyebrow="__('Konto')"
    icon="👤"
    tone="info">
    <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-4" data-entry-form>
        @csrf
        @method('PUT')
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ route('account.profile.edit') }}?dialog=1">
        @endif

        <div>
            <label class="label" for="name">
                <span class="label-text">{{ __('Name') }}</span>
            </label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="input input-bordered w-full" required>
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="label" for="email">
                <span class="label-text">{{ __('E-Mail') }}</span>
            </label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="input input-bordered w-full" required>
            @error('email')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-sm btn-primary">{{ __('Speichern') }}</button>
            @if ($isDialog)
                <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Schließen') }}</button>
            @endif
            <a href="{{ route('account.password.edit') }}" data-entry-modal-trigger class="btn btn-sm btn-ghost">{{ __('Passwort ändern') }}</a>
        </div>
    </form>
</x-dialog>
