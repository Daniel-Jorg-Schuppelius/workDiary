{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : admin.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.install')

@section('install-content')
<h2 class="card-title mb-2">{{ __('Organisation & Administrator') }}</h2>
<p class="mb-4 text-sm text-base-content/70">
    {{ __('Legen Sie die erste Organisation und das Administrator-Konto an.') }}
</p>

<form method="POST" action="{{ route('install.admin.store') }}" class="space-y-4">
    @csrf

    <fieldset class="fieldset">
        <label class="fieldset-label" for="org_name">{{ __('Name der Organisation') }}</label>
        <input type="text" name="org_name" id="org_name" value="{{ old('org_name') }}"
               class="input input-sm input-bordered w-full" required>
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
        <fieldset class="fieldset">
            <label class="fieldset-label" for="name">{{ __('Name') }}</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}"
                   class="input input-sm input-bordered w-full" required>
        </fieldset>
        <fieldset class="fieldset">
            <label class="fieldset-label" for="email">{{ __('E-Mail') }}</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                   class="input input-sm input-bordered w-full" required>
        </fieldset>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <fieldset class="fieldset">
            <label class="fieldset-label" for="password">{{ __('Passwort') }}</label>
            <input type="password" name="password" id="password"
                   class="input input-sm input-bordered w-full" autocomplete="new-password" required>
        </fieldset>
        <fieldset class="fieldset">
            <label class="fieldset-label" for="password_confirmation">{{ __('Passwort bestätigen') }}</label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                   class="input input-sm input-bordered w-full" autocomplete="new-password" required>
        </fieldset>
    </div>

    <div class="card-actions justify-between pt-2">
        <span></span>
        <x-button type="submit" tone="primary" size="sm" iconTrailing="arrow_forward">{{ __('Administrator anlegen') }}</x-button>
    </div>
</form>
@endsection
