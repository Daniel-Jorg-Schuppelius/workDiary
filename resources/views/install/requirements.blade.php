{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : requirements.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.install')

@section('install-content')
<h2 class="card-title mb-2">{{ __('Systemvoraussetzungen') }}</h2>
<p class="mb-4 text-sm text-base-content/70">{{ __('Prüfen Sie, ob Ihr Server alle Anforderungen erfüllt.') }}</p>

<form method="GET" action="{{ route('install.index') }}" class="mb-4 flex items-center gap-2">
    <label class="text-sm" for="driver">{{ __('Datenbank-Treiber') }}</label>
    <select name="driver" id="driver" class="select select-sm select-bordered w-40" data-autosubmit>
        @foreach (['sqlite', 'mysql', 'pgsql'] as $d)
            <option value="{{ $d }}" @selected($driver === $d)>{{ $d }}</option>
        @endforeach
    </select>
    {{-- Immer vorhandener Submit-Fallback: data-autosubmit deckt nur Zeiger/JS ab; Tastatur-/No-JS-Nutzer brauchen einen echten Absende-Button (WCAG2AA). --}}
    <button type="submit" class="btn btn-sm">{{ __('Aktualisieren') }}</button>
</form>

<div class="overflow-x-auto">
    <table class="table table-sm">
        <tbody>
            @foreach ($checks as $check)
                <tr>
                    <td class="w-8">
                        @if ($check['ok'])
                            <span class="material-symbols-outlined text-success" aria-hidden="true">check_circle</span>
                        @else
                            <span class="material-symbols-outlined text-error" aria-hidden="true">cancel</span>
                        @endif
                    </td>
                    <td>{{ $check['label'] }}</td>
                    <td class="text-xs text-base-content/60">{{ $check['ok'] ? '' : $check['hint'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="card-actions mt-6 justify-end">
    @if ($met)
        <x-button href="{{ route('install.application') }}" tone="primary" size="sm" iconTrailing="arrow_forward">{{ __('Weiter') }}</x-button>
    @else
        <span class="text-sm text-error">{{ __('Bitte beheben Sie die markierten Punkte und laden Sie die Seite neu.') }}</span>
    @endif
</div>
@endsection
