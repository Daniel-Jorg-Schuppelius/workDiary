@extends('layouts.install')

@section('install-content')
<h2 class="card-title mb-2">{{ __('Anwendungseinstellungen') }}</h2>
<p class="mb-4 text-sm text-base-content/70">
    {{ __('Grundlegende Angaben zur Anwendung. Ein Anwendungsschlüssel (APP_KEY) wird automatisch erzeugt, sofern noch keiner existiert.') }}
</p>

@if ($hasAppKey)
    <div class="alert alert-info mb-4">
        <span class="material-symbols-outlined" aria-hidden="true">key</span>
        <span>{{ __('Ein Anwendungsschlüssel ist bereits vorhanden und wird nicht überschrieben.') }}</span>
    </div>
@endif

<form method="POST" action="{{ route('install.application.store') }}" class="space-y-4">
    @csrf

    <fieldset class="fieldset">
        <label class="fieldset-label" for="app_name">{{ __('Anwendungsname') }}</label>
        <input type="text" name="app_name" id="app_name" value="{{ old('app_name', $values['app_name']) }}"
               class="input input-sm input-bordered w-full" required>
    </fieldset>

    <fieldset class="fieldset">
        <label class="fieldset-label" for="app_url">{{ __('Anwendungs-URL') }}</label>
        <input type="url" name="app_url" id="app_url" value="{{ old('app_url', $values['app_url']) }}"
               class="input input-sm input-bordered w-full" required>
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-3">
        <fieldset class="fieldset">
            <label class="fieldset-label" for="app_env">{{ __('Umgebung') }}</label>
            <select name="app_env" id="app_env" class="select select-sm select-bordered w-full">
                <option value="production" @selected(old('app_env', $values['app_env']) === 'production')>production</option>
                <option value="local" @selected(old('app_env', $values['app_env']) === 'local')>local</option>
            </select>
        </fieldset>
        <fieldset class="fieldset">
            <label class="fieldset-label" for="locale">{{ __('Sprache') }}</label>
            <input type="text" name="locale" id="locale" value="{{ old('locale', $values['locale']) }}"
                   class="input input-sm input-bordered w-full" required>
        </fieldset>
        <fieldset class="fieldset">
            <label class="fieldset-label" for="timezone">{{ __('Zeitzone') }}</label>
            <input type="text" name="timezone" id="timezone" value="{{ old('timezone', $values['timezone']) }}"
                   class="input input-sm input-bordered w-full" required>
        </fieldset>
    </div>

    <div class="card-actions justify-between pt-2">
        <a href="{{ route('install.index') }}" class="btn btn-sm btn-ghost">{{ __('Zurück') }}</a>
        <button type="submit" class="btn btn-sm btn-primary">
            {{ __('Weiter') }}
            <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
        </button>
    </div>
</form>
@endsection
