{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Shared form fields for create & edit --}}
@php
    $canManageMembers = $canManageMembers ?? true;
    $canManagePayroll = $canManagePayroll ?? false;
@endphp

@if ($canManageMembers)
    <x-form-group :legend="__('Mitarbeiterdaten')" icon="person" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Anzeigename')" required span="2" maxlength="255" autofocus :value="old('name', $member?->name)" />

        <x-input-field name="personnel_number" :label="__('Personalnummer')" maxlength="64" :value="old('personnel_number', $member?->personnel_number)" />

        <x-input-field name="email" type="email" :label="__('E-Mail-Adresse')" required maxlength="255" :value="old('email', $member?->email)" />

        {{-- Neue Mitarbeiter starten mit der kleinsten Rolle, nicht mit Admin. --}}
        <x-select-field name="role" :label="__('Rolle')" required>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected(old('role', $member?->roles->first()?->name ?? \App\Enums\User\UserRole::User->value) === $role)>
                    {{ \App\Support\Trans::or("user.role.{$role}", $role) }}
                </option>
            @endforeach
        </x-select-field>

        @isset($deputyOptions)
            {{-- MVP-523: Stellvertretung übernimmt Urlaubs-Entscheidungen, solange
                 diese Person eine genehmigte Abwesenheit hat. --}}
            <x-select-field name="deputy_user_id" span="2" :label="__('Stellvertretung (bei Abwesenheit)')">
                <option value="">{{ __('— keine —') }}</option>
                @foreach ($deputyOptions as $deputy)
                    <option value="{{ $deputy->sqid }}" @selected((string) old('deputy_user_id', $member?->deputy_user_id ? \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $member->deputy_user_id) : '') === $deputy->sqid)>
                        {{ $deputy->name }}
                    </option>
                @endforeach
            </x-select-field>
        @endisset
    </x-form-group>

    @include('org.members._payroll_fields')

    @include('users._contact_fields', ['user' => $member ?? null])

    @if (! isset($member))
        <x-form-group :legend="__('Initial-Passwort')" icon="lock" tone="warning" cols="2"
                      :description="__('Der Mitarbeiter wird beim ersten Login aufgefordert, das Passwort zu ändern.')">
            <x-input-field name="password" type="password" :label="__('Passwort')" required minlength="12" autocomplete="new-password"
                           :placeholder="__('Mindestens 12 Zeichen')" :hint="__('Mit Groß- und Kleinbuchstaben, Ziffer und Sonderzeichen.')" />
            <x-input-field name="password_confirmation" type="password" :label="__('Passwort bestätigen')" required minlength="12" autocomplete="new-password" />
            <div class="md:col-span-2">
                <x-button tone="ghost" icon="key" data-password-suggest="password password_confirmation">{{ __('Passwort vorschlagen') }}</x-button>
            </div>
        </x-form-group>
    @else
        <x-form-group :legend="__('Passwort zurücksetzen')" icon="lock_reset" tone="warning" cols="2"
                      :description="__('Leer lassen = unverändert. Sonst muss der Mitarbeiter es beim nächsten Login ändern.')">
            <x-input-field name="new_password" type="password" :label="__('Neues Passwort')" autocomplete="new-password" minlength="12"
                           :placeholder="__('Mindestens 12 Zeichen')" :hint="__('Mit Groß- und Kleinbuchstaben, Ziffer und Sonderzeichen.')" />
            <x-input-field name="new_password_confirmation" type="password" :label="__('Bestätigen')" autocomplete="new-password" minlength="12" />
            <div class="md:col-span-2">
                <x-button tone="ghost" icon="key" data-password-suggest="new_password new_password_confirmation">{{ __('Passwort vorschlagen') }}</x-button>
            </div>
        </x-form-group>
    @endif
@elseif ($canManagePayroll && $member)
    {{-- Personalverwaltung/Geschäftsführung: Identität read-only, voller Personal-/Payroll-Block editierbar. --}}
    <x-form-group :legend="__('Mitarbeiter')" icon="person" tone="primary" cols="2">
        <div class="fieldset md:col-span-2">
            <label for="member-name-readonly" class="fieldset-label">{{ __('Mitarbeiter') }}</label>
            <input id="member-name-readonly" type="text" class="input input-bordered w-full" value="{{ $member->name }}" disabled>
        </div>
    </x-form-group>

    @include('org.members._payroll_fields')
@endif
