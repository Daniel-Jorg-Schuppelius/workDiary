@extends('layouts.install')

@section('install-content')
<h2 class="card-title mb-2">{{ __('E-Mail / SMTP') }}</h2>
<p class="mb-4 text-sm text-base-content/70">
    {{ __('Konfigurieren Sie den Mailversand. Mit "log" werden E-Mails nur protokolliert (ohne Versand).') }}
</p>

<form method="POST" action="{{ route('install.mail.store') }}" class="space-y-4">
    @csrf

    <fieldset class="fieldset">
        <label class="fieldset-label" for="mailer">{{ __('Mailer') }}</label>
        <select name="mailer" id="mailer" class="select select-sm select-bordered w-full">
            <option value="log" @selected(old('mailer', $values['mailer']) === 'log')>log</option>
            <option value="smtp" @selected(old('mailer', $values['mailer']) === 'smtp')>smtp</option>
        </select>
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
        <fieldset class="fieldset">
            <label class="fieldset-label" for="host">{{ __('SMTP-Host') }}</label>
            <input type="text" name="host" id="host" value="{{ old('host', $values['host']) }}"
                   class="input input-sm input-bordered w-full">
        </fieldset>
        <fieldset class="fieldset">
            <label class="fieldset-label" for="port">{{ __('Port') }}</label>
            <input type="number" name="port" id="port" value="{{ old('port', $values['port']) }}"
                   class="input input-sm input-bordered w-full">
        </fieldset>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <fieldset class="fieldset">
            <label class="fieldset-label" for="username">{{ __('Benutzer') }}</label>
            <input type="text" name="username" id="username" value="{{ old('username', $values['username']) }}"
                   class="input input-sm input-bordered w-full">
        </fieldset>
        <fieldset class="fieldset">
            <label class="fieldset-label" for="password">{{ __('Passwort') }}</label>
            <input type="password" name="password" id="password" value=""
                   class="input input-sm input-bordered w-full" autocomplete="new-password">
        </fieldset>
        <fieldset class="fieldset">
            <label class="fieldset-label" for="scheme">{{ __('Verschlüsselung') }}</label>
            <select name="scheme" id="scheme" class="select select-sm select-bordered w-full">
                <option value="" @selected(old('scheme', $values['scheme']) === '')>{{ __('keine') }}</option>
                <option value="tls" @selected(old('scheme', $values['scheme']) === 'tls')>tls</option>
                <option value="ssl" @selected(old('scheme', $values['scheme']) === 'ssl')>ssl</option>
            </select>
        </fieldset>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <fieldset class="fieldset">
            <label class="fieldset-label" for="from_address">{{ __('Absender-Adresse') }}</label>
            <input type="email" name="from_address" id="from_address" value="{{ old('from_address', $values['from_address']) }}"
                   class="input input-sm input-bordered w-full" required>
        </fieldset>
        <fieldset class="fieldset">
            <label class="fieldset-label" for="from_name">{{ __('Absender-Name') }}</label>
            <input type="text" name="from_name" id="from_name" value="{{ old('from_name', $values['from_name']) }}"
                   class="input input-sm input-bordered w-full" required>
        </fieldset>
    </div>

    <div class="card-actions justify-between pt-2">
        <a href="{{ route('install.admin') }}" class="btn btn-sm btn-ghost">{{ __('Zurück') }}</a>
        <button type="submit" class="btn btn-sm btn-primary">
            {{ __('Weiter') }}
            <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
        </button>
    </div>
</form>
@endsection
