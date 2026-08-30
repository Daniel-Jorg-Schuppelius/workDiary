{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : issuer.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Lizenzen ausstellen') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Lizenzen ausstellen'))

@php
    /** @var list<string> $moduleCodes */
    $issuedKey = session('issued_key');
    $issuedMeta = session('issued_meta');
@endphp

@section('content')
<x-index-page :subtitle="__('Signierte Lizenzen für Kunden erstellen und verteilen')">
    <x-slot:actions>
        <x-button :href="route('admin.license.index')" tone="ghost" size="sm">{{ __('Zurück zur Lizenz') }}</x-button>
    </x-slot:actions>

    @if ($issuedKey)
        <article class="card border border-success/40 bg-success/5 shadow-sm">
            <div class="card-body gap-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold text-success">{{ __('Lizenz erstellt') }}</h2>
                <p class="text-sm text-base-content/70">
                    {{ __('Für') }} <span class="font-semibold">{{ $issuedMeta['licensee'] ?? '' }}</span>
                    · {{ __('Plan') }}: {{ __('values.' . ($issuedMeta['plan'] ?? 'free')) }}.
                    {{ __('Diesen Schlüssel an den Kunden weitergeben – er installiert ihn auf seiner Instanz.') }}
                </p>
                <textarea id="issued-key" rows="4" readonly class="textarea textarea-bordered w-full font-mono text-xs select-all">{{ $issuedKey }}</textarea>
                <div class="flex flex-wrap gap-2">
                    <x-button type="button" tone="primary" size="sm"
                        data-copy-target="issued-key" data-copy-feedback="{{ __('Kopiert ✓') }}">
                        {{ __('In Zwischenablage kopieren') }}
                    </x-button>
                    <x-button tone="ghost" size="sm" download="license.key"
                        href="data:text/plain;charset=utf-8,{{ rawurlencode($issuedKey) }}">{{ __('Als Datei herunterladen') }}</x-button>
                </div>
                <p class="text-xs text-muted">{{ __('Aus Sicherheitsgründen wird der Schlüssel nur jetzt angezeigt und nicht gespeichert.') }}</p>
            </div>
        </article>
    @endif

    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-4">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Neue Lizenz') }}</h2>
            @error('issue')<p class="text-sm text-error">{{ $message }}</p>@enderror

            <form method="POST" action="{{ route('admin.license.issuer.create') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs uppercase tracking-wider text-muted">{{ __('Lizenznehmer') }} *</label>
                        <input type="text" name="licensee" required value="{{ old('licensee') }}"
                            class="input input-sm input-bordered w-full @error('licensee') input-error @enderror">
                        @error('licensee')<p class="text-xs text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-wider text-muted">{{ __('E-Mail (optional)') }}</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                            class="input input-sm input-bordered w-full @error('email') input-error @enderror">
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-wider text-muted">{{ __('Plan (Tier)') }}</label>
                        <select name="plan" class="select select-sm select-bordered w-full">
                            @foreach (['free', 'pro', 'enterprise'] as $val)
                                <option value="{{ $val }}" @selected(old('plan', 'enterprise') === $val)>{{ __('values.' . $val) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-wider text-muted">{{ __('Gültig bis (optional)') }}</label>
                        <input type="date" name="expires" value="{{ old('expires') }}"
                            class="input input-sm input-bordered w-full @error('expires') input-error @enderror">
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-wider text-muted">{{ __('Max. Nutzer (optional)') }}</label>
                        <input type="number" name="max_users" min="1" value="{{ old('max_users') }}"
                            class="input input-sm input-bordered w-full @error('max_users') input-error @enderror">
                    </div>
                </div>

                <div class="rounded-box border border-base-300 bg-base-200/50 p-3 space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted">{{ __('Bindung (empfohlen)') }}</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-xs text-muted">{{ __('Org-Bindungs-ID des Kunden (license_uid)') }}</label>
                            <input aria-label="{{ __('Kennung der Organisation') }}" type="text" name="organization_uid" value="{{ old('organization_uid') }}"
                                class="input input-sm input-bordered w-full font-mono text-xs" placeholder="z. B. 0fa75312-…">
                        </div>
                        <div>
                            <label class="text-xs text-muted">{{ __('Domain-Bindung') }}</label>
                            <input aria-label="{{ __('Erlaubte Domain') }}" type="text" name="domain" value="{{ old('domain') }}"
                                class="input input-sm input-bordered w-full font-mono text-xs" placeholder="app.kunde.de oder *.kunde.de">
                        </div>
                    </div>
                    <p class="text-xs text-muted">{{ __('Ohne Bindung ist die Lizenz auf jeder Instanz nutzbar – nur wenn bewusst gewünscht leer lassen.') }}</p>
                </div>

                <div>
                    <label class="text-xs uppercase tracking-wider text-muted">{{ __('Einzeln gebuchte Module (Add-ons)') }}</label>
                    <p class="text-xs text-muted">{{ __('Tier-Module sind enthalten; hier nur zusätzliche Module über das Tier hinaus.') }}</p>
                    <div class="mt-1 grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3">
                        @php $oldAddons = (array) old('addons', []); @endphp
                        @foreach ($moduleCodes as $code)
                            <label class="label cursor-pointer justify-start gap-2 py-0.5">
                                <input type="checkbox" name="addons[]" value="{{ $code }}" class="checkbox checkbox-xs" @checked(in_array($code, $oldAddons, true))>
                                <span class="text-sm">{{ config('plans.labels')[$code] ?? $code }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <x-button type="submit" tone="primary" size="sm">{{ __('Lizenz erstellen') }}</x-button>
            </form>
        </div>
    </article>
</x-index-page>
@endsection
