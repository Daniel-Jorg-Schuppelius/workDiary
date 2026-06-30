@extends('layouts.app')

@section('title', __('Meldeportal verwalten'))
@section('nav-title', __('Hinweisgeber-Meldeportal'))

@section('content')
    <x-index-page :subtitle="__('Öffentliches Meldeportal für Hinweisgeber konfigurieren.')">
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
                    <p class="text-sm text-base-content/70">{{ __('Diesen Link veröffentlichen Sie für Hinweisgeber. Er ist nicht aus dem Organisationsnamen ableitbar.') }}</p>
                    <form method="post" action="{{ route('whistleblowing.portal.rotate') }}"
                          data-confirm-dialog
                          data-confirm-icon="autorenew"
                          data-confirm-tone="warning"
                          data-confirm-message="{{ __('Link wirklich rotieren? Bereits verteilte Links werden ungültig.') }}">
                        @csrf
                        <x-icon-btn icon="autorenew" tone="ghost" size="sm" type="submit" show-label>{{ __('Link rotieren') }}</x-icon-btn>
                    </form>
                </div>
            </x-card>
        @else
            <div class="alert">{{ __('Es ist noch kein Meldeportal angelegt. Speichern Sie, um eines mit einem zufälligen Link zu erstellen.') }}</div>
        @endif

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Einstellungen') }}</h2>
            <form method="post" action="{{ route('whistleblowing.portal.update') }}" class="mt-2 space-y-3">
                @csrf
                @method('PUT')

                <x-form-group :legend="__('Sichtbarkeit')" icon="visibility" tone="primary" cols="1">
                    <x-input-field name="is_enabled">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" id="is_enabled" name="is_enabled" value="1" class="checkbox checkbox-sm" @checked(old('is_enabled', $portal->is_enabled))>
                            <span class="fieldset-label">{{ __('Portal aktiv (öffentlich erreichbar)') }}</span>
                        </label>
                    </x-input-field>
                    <x-input-field name="allow_anonymous">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" id="allow_anonymous" name="allow_anonymous" value="1" class="checkbox checkbox-sm" @checked(old('allow_anonymous', $portal->allow_anonymous ?? true))>
                            <span class="fieldset-label">{{ __('Anonyme Meldungen erlauben') }}</span>
                        </label>
                    </x-input-field>
                    <x-input-field name="allow_confidential">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" id="allow_confidential" name="allow_confidential" value="1" class="checkbox checkbox-sm" @checked(old('allow_confidential', $portal->allow_confidential ?? true))>
                            <span class="fieldset-label">{{ __('Vertrauliche Meldungen erlauben') }}</span>
                        </label>
                    </x-input-field>
                </x-form-group>

                <x-form-group :legend="__('Darstellung & Aufbewahrung')" icon="tune" tone="ghost" cols="2">
                    <x-input-field name="intro_text" :label="__('Einleitungstext (optional)')" span="2">
                        <textarea id="intro_text" name="intro_text" rows="4" class="textarea textarea-bordered w-full">{{ old('intro_text', $portal->intro_text) }}</textarea>
                    </x-input-field>
                    <x-input-field name="default_locale" :label="__('Standardsprache (optional, z. B. de)')" :value="old('default_locale', $portal->default_locale)" />
                    <x-input-field name="retention_months" type="number" :label="__('Aufbewahrung nach Abschluss (Monate)')" :value="old('retention_months', $portal->retention_months ?? 36)" required min="1" max="600" />
                </x-form-group>

                <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('Speichern') }}</x-icon-btn>
            </form>
        </x-card>
    </x-index-page>
@endsection
