{{-- Shared form fields for create & edit --}}

<x-form-group :legend="__('Mitgliedsdaten')" icon="person" tone="primary" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Vollständiger Name') }} *</label>
        <input type="text" name="name" class="input input-bordered w-full @error('name') input-error @enderror"
               value="{{ old('name', $member?->name) }}" required maxlength="255" autofocus>
        @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('E-Mail-Adresse') }} *</label>
        <input type="email" name="email" class="input input-bordered w-full @error('email') input-error @enderror"
               value="{{ old('email', $member?->email) }}" required maxlength="255">
        @error('email')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Rolle') }} *</label>
        <select name="role" class="select select-bordered w-full @error('role') select-error @enderror" required>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected(old('role', $member?->roles->first()?->name) === $role)>
                    {{ ucfirst($role) }}
                </option>
            @endforeach
        </select>
        @error('role')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

@if (! isset($member))
    <x-form-group :legend="__('Initial-Passwort')" icon="lock" tone="warning" cols="2"
                  :description="__('Das Mitglied wird beim ersten Login aufgefordert, das Passwort zu ändern.')">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Passwort') }} *</label>
            <input type="password" name="password" class="input input-bordered w-full @error('password') input-error @enderror"
                   required minlength="8" placeholder="{{ __('Mindestens 8 Zeichen') }}">
            @error('password')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Passwort bestätigen') }} *</label>
            <input type="password" name="password_confirmation" class="input input-bordered w-full" required minlength="8">
        </div>
    </x-form-group>
@endif
