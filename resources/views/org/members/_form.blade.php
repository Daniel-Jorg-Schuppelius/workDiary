{{-- Shared form fields for create & edit --}}
@php
    $canManageMembers = $canManageMembers ?? true;
    $canManagePayroll = $canManagePayroll ?? false;
@endphp

@if ($canManageMembers)
    <x-form-group :legend="__('Mitarbeiterdaten')" icon="person" tone="primary" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Anzeigename') }} *</label>
            <input type="text" name="name" class="input input-bordered w-full @error('name') input-error @enderror"
                   value="{{ old('name', $member?->name) }}" required maxlength="255" autofocus>
            @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('Personalnummer') }}</label>
            <input type="text" name="personnel_number" class="input input-bordered w-full @error('personnel_number') input-error @enderror"
                   value="{{ old('personnel_number', $member?->personnel_number) }}" maxlength="64">
            @error('personnel_number')<p class="text-error text-sm">{{ $message }}</p>@enderror
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

    @include('org.members._payroll_fields')

    @include('users._contact_fields', ['user' => $member ?? null])

    @if (! isset($member))
        <x-form-group :legend="__('Initial-Passwort')" icon="lock" tone="warning" cols="2"
                      :description="__('Der Mitarbeiter wird beim ersten Login aufgefordert, das Passwort zu ändern.')">
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
    @else
        <x-form-group :legend="__('Passwort zurücksetzen')" icon="lock_reset" tone="warning" cols="2"
                      :description="__('Leer lassen = unverändert. Sonst muss der Mitarbeiter es beim nächsten Login ändern.')">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Neues Passwort') }}</label>
                <input type="password" name="new_password" autocomplete="new-password" minlength="8"
                       class="input input-bordered w-full @error('new_password') input-error @enderror"
                       placeholder="{{ __('Mindestens 8 Zeichen') }}">
                @error('new_password')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Bestätigen') }}</label>
                <input type="password" name="new_password_confirmation" autocomplete="new-password" minlength="8"
                       class="input input-bordered w-full">
            </div>
        </x-form-group>
    @endif
@elseif ($canManagePayroll && $member)
    {{-- Personalverwaltung/Geschäftsführung: Identität read-only, voller Personal-/Payroll-Block editierbar. --}}
    <x-form-group :legend="__('Mitarbeiter')" icon="person" tone="primary" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Mitarbeiter') }}</label>
            <input type="text" class="input input-bordered w-full" value="{{ $member->name }}" disabled>
        </div>
    </x-form-group>

    @include('org.members._payroll_fields')
@endif
