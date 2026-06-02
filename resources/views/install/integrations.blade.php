@extends('layouts.install')

@section('install-content')
<h2 class="card-title mb-2">{{ __('Integrationen (optional)') }}</h2>
<p class="mb-4 text-sm text-base-content/70">
    {{ __('Diese Angaben sind optional und können später jederzeit ergänzt werden.') }}
</p>

<form method="POST" action="{{ route('install.integrations.store') }}" class="space-y-4">
    @csrf

    <fieldset class="fieldset">
        <label class="fieldset-label" for="lexoffice_api_key">{{ __('Lexoffice API-Schlüssel') }}</label>
        <input type="text" name="lexoffice_api_key" id="lexoffice_api_key" value="{{ old('lexoffice_api_key', $values['lexoffice_api_key']) }}"
               class="input input-sm input-bordered w-full" autocomplete="off">
    </fieldset>

    <div class="divider text-xs">{{ __('Web-Push (VAPID)') }}</div>

    <fieldset class="fieldset">
        <label class="fieldset-label" for="vapid_subject">{{ __('VAPID Subject') }}</label>
        <input type="text" name="vapid_subject" id="vapid_subject" value="{{ old('vapid_subject', $values['vapid_subject']) }}"
               class="input input-sm input-bordered w-full">
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
        <fieldset class="fieldset">
            <label class="fieldset-label" for="vapid_public_key">{{ __('Public Key') }}</label>
            <input type="text" name="vapid_public_key" id="vapid_public_key" value="{{ old('vapid_public_key', $values['vapid_public_key']) }}"
                   class="input input-sm input-bordered w-full" autocomplete="off">
        </fieldset>
        <fieldset class="fieldset">
            <label class="fieldset-label" for="vapid_private_key">{{ __('Private Key') }}</label>
            <input type="text" name="vapid_private_key" id="vapid_private_key" value="{{ old('vapid_private_key', $values['vapid_private_key']) }}"
                   class="input input-sm input-bordered w-full" autocomplete="off">
        </fieldset>
    </div>

    <div class="card-actions justify-between pt-2">
        <a href="{{ route('install.mail') }}" class="btn btn-sm btn-ghost">{{ __('Zurück') }}</a>
        <button type="submit" class="btn btn-sm btn-primary">
            {{ __('Weiter') }}
            <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
        </button>
    </div>
</form>
@endsection
