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
        <button type="submit" class="btn btn-sm btn-primary">
            {{ __('Administrator anlegen') }}
            <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
        </button>
    </div>
</form>
@endsection
