{{-- Shared form fields for create & edit --}}

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Vollständiger Name') }} *</span></label>
    <input type="text" name="name" class="input input-bordered @error('name') input-error @enderror"
           value="{{ old('name', $member?->name) }}" required maxlength="255" autofocus>
    @error('name')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('E-Mail-Adresse') }} *</span></label>
    <input type="email" name="email" class="input input-bordered @error('email') input-error @enderror"
           value="{{ old('email', $member?->email) }}" required maxlength="255">
    @error('email')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>

<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Rolle') }} *</span></label>
    <select name="role" class="select select-bordered @error('role') select-error @enderror" required>
        @foreach ($roles as $role)
            <option value="{{ $role }}" @selected(old('role', $member?->roles->first()?->name) === $role)>
                {{ ucfirst($role) }}
            </option>
        @endforeach
    </select>
    @error('role')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>

@if (! isset($member))
<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Passwort') }} *</span></label>
    <input type="password" name="password" class="input input-bordered @error('password') input-error @enderror"
           required minlength="8" placeholder="{{ __('Mindestens 8 Zeichen') }}">
    @error('password')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>
<div class="form-control">
    <label class="label"><span class="label-text">{{ __('Passwort bestätigen') }} *</span></label>
    <input type="password" name="password_confirmation" class="input input-bordered" required minlength="8">
</div>
<p class="text-xs text-base-content/60">{{ __('Das Mitglied wird beim ersten Login aufgefordert, das Passwort zu ändern.') }}</p>
@endif
