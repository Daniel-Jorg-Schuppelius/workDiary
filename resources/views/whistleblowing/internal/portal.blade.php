@extends('layouts.app')

@section('title', __('Meldeportal verwalten'))
@section('nav-title', __('Hinweisgeber-Meldeportal'))

@section('content')
    <x-index-page :subtitle="__('Oeffentliches Meldeportal fuer Hinweisgeber konfigurieren.')">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @if ($portal->exists)
            <x-card>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Oeffentlicher Link') }}</h2>
                <div class="mt-2 space-y-2">
                    <p>
                        <code>{{ url('/melden/' . $portal->public_slug) }}</code>
                        <x-status-badge :tone="$portal->is_enabled ? 'success' : 'ghost'" size="sm">
                            {{ $portal->is_enabled ? __('aktiv') : __('inaktiv') }}
                        </x-status-badge>
                    </p>
                    <p class="text-sm text-base-content/70">{{ __('Diesen Link veroeffentlichen Sie fuer Hinweisgeber. Er ist nicht aus dem Organisationsnamen ableitbar.') }}</p>
                    <form method="post" action="{{ route('whistleblowing.portal.rotate') }}"
                          onsubmit="return confirm('{{ __('Link wirklich rotieren? Bereits verteilte Links werden ungueltig.') }}')">
                        @csrf
                        <x-icon-btn icon="autorenew" tone="ghost" size="sm" type="submit" show-label>{{ __('Link rotieren') }}</x-icon-btn>
                    </form>
                </div>
            </x-card>
        @else
            <div class="alert">{{ __('Es ist noch kein Meldeportal angelegt. Speichern Sie, um eines mit einem zufaelligen Link zu erstellen.') }}</div>
        @endif

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Einstellungen') }}</h2>
            <form method="post" action="{{ route('whistleblowing.portal.update') }}" class="mt-2 space-y-3">
                @csrf
                @method('PUT')

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_enabled" value="1" class="checkbox" @checked(old('is_enabled', $portal->is_enabled))>
                    {{ __('Portal aktiv (oeffentlich erreichbar)') }}
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="allow_anonymous" value="1" class="checkbox" @checked(old('allow_anonymous', $portal->allow_anonymous ?? true))>
                    {{ __('Anonyme Meldungen erlauben') }}
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="allow_confidential" value="1" class="checkbox" @checked(old('allow_confidential', $portal->allow_confidential ?? true))>
                    {{ __('Vertrauliche Meldungen erlauben') }}
                </label>

                <div>
                    <label class="font-semibold" for="intro_text">{{ __('Einleitungstext (optional)') }}</label>
                    <textarea id="intro_text" name="intro_text" rows="4" class="textarea textarea-bordered w-full">{{ old('intro_text', $portal->intro_text) }}</textarea>
                </div>

                <div>
                    <label class="font-semibold" for="default_locale">{{ __('Standardsprache (optional, z. B. de)') }}</label>
                    <input id="default_locale" name="default_locale" class="input input-bordered w-full" value="{{ old('default_locale', $portal->default_locale) }}">
                </div>

                <div>
                    <label class="font-semibold" for="retention_months">{{ __('Aufbewahrung nach Abschluss (Monate)') }}</label>
                    <input id="retention_months" name="retention_months" type="number" min="1" max="600" class="input input-bordered w-full"
                           value="{{ old('retention_months', $portal->retention_months ?? 36) }}" required>
                </div>

                <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('Speichern') }}</x-icon-btn>
            </form>
        </x-card>
    </x-index-page>
@endsection
