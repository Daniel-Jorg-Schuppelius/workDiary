@extends('layouts.install')

@section('install-content')
<h2 class="card-title mb-2">{{ __('Integrationen (optional)') }}</h2>
<p class="mb-4 text-sm text-base-content/70">
    {{ __('Diese Angaben sind optional und können später jederzeit ergänzt werden.') }}
</p>

<form method="POST" action="{{ route('install.integrations.store') }}" class="space-y-4">
    @csrf

    <div>
        <label class="label" for="lexoffice_api_key"><span class="label-text">{{ __('Lexoffice API-Schlüssel') }}</span></label>
        <input type="text" name="lexoffice_api_key" id="lexoffice_api_key" value="{{ old('lexoffice_api_key', $values['lexoffice_api_key']) }}"
               class="input input-sm input-bordered w-full" autocomplete="off">
    </div>

    <div class="divider text-xs">{{ __('Web-Push (VAPID)') }}</div>

    <div>
        <label class="label" for="vapid_subject"><span class="label-text">{{ __('VAPID Subject') }}</span></label>
        <input type="text" name="vapid_subject" id="vapid_subject" value="{{ old('vapid_subject', $values['vapid_subject']) }}"
               class="input input-sm input-bordered w-full">
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label" for="vapid_public_key"><span class="label-text">{{ __('Public Key') }}</span></label>
            <input type="text" name="vapid_public_key" id="vapid_public_key" value="{{ old('vapid_public_key', $values['vapid_public_key']) }}"
                   class="input input-sm input-bordered w-full" autocomplete="off">
        </div>
        <div>
            <label class="label" for="vapid_private_key"><span class="label-text">{{ __('Private Key') }}</span></label>
            <input type="text" name="vapid_private_key" id="vapid_private_key" value="{{ old('vapid_private_key', $values['vapid_private_key']) }}"
                   class="input input-sm input-bordered w-full" autocomplete="off">
        </div>
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
