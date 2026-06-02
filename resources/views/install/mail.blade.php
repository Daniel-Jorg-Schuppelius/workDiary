@extends('layouts.install')

@section('install-content')
<h2 class="card-title mb-2">{{ __('E-Mail / SMTP') }}</h2>
<p class="mb-4 text-sm text-base-content/70">
    {{ __('Konfigurieren Sie den Mailversand. Mit "log" werden E-Mails nur protokolliert (ohne Versand).') }}
</p>

<form method="POST" action="{{ route('install.mail.store') }}" class="space-y-4">
    @csrf

    <div>
        <label class="label" for="mailer"><span class="label-text">{{ __('Mailer') }}</span></label>
        <select name="mailer" id="mailer" class="select select-sm select-bordered w-full">
            <option value="log" @selected(old('mailer', $values['mailer']) === 'log')>log</option>
            <option value="smtp" @selected(old('mailer', $values['mailer']) === 'smtp')>smtp</option>
        </select>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label" for="host"><span class="label-text">{{ __('SMTP-Host') }}</span></label>
            <input type="text" name="host" id="host" value="{{ old('host', $values['host']) }}"
                   class="input input-sm input-bordered w-full">
        </div>
        <div>
            <label class="label" for="port"><span class="label-text">{{ __('Port') }}</span></label>
            <input type="number" name="port" id="port" value="{{ old('port', $values['port']) }}"
                   class="input input-sm input-bordered w-full">
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label class="label" for="username"><span class="label-text">{{ __('Benutzer') }}</span></label>
            <input type="text" name="username" id="username" value="{{ old('username', $values['username']) }}"
                   class="input input-sm input-bordered w-full">
        </div>
        <div>
            <label class="label" for="password"><span class="label-text">{{ __('Passwort') }}</span></label>
            <input type="password" name="password" id="password" value=""
                   class="input input-sm input-bordered w-full" autocomplete="new-password">
        </div>
        <div>
            <label class="label" for="scheme"><span class="label-text">{{ __('Verschlüsselung') }}</span></label>
            <select name="scheme" id="scheme" class="select select-sm select-bordered w-full">
                <option value="" @selected(old('scheme', $values['scheme']) === '')>{{ __('keine') }}</option>
                <option value="tls" @selected(old('scheme', $values['scheme']) === 'tls')>tls</option>
                <option value="ssl" @selected(old('scheme', $values['scheme']) === 'ssl')>ssl</option>
            </select>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label" for="from_address"><span class="label-text">{{ __('Absender-Adresse') }}</span></label>
            <input type="email" name="from_address" id="from_address" value="{{ old('from_address', $values['from_address']) }}"
                   class="input input-sm input-bordered w-full" required>
        </div>
        <div>
            <label class="label" for="from_name"><span class="label-text">{{ __('Absender-Name') }}</span></label>
            <input type="text" name="from_name" id="from_name" value="{{ old('from_name', $values['from_name']) }}"
                   class="input input-sm input-bordered w-full" required>
        </div>
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
