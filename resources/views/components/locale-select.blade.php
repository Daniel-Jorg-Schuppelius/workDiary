{{--
  Created on   : Sat Jun 06 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : locale-select.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'name' => 'locale',
    'selected' => null,        // aktuell gewählter Locale-Code
    'includeBlank' => false,   // true → leere Option (z. B. "Organisation übernehmen")
    'blankLabel' => null,      // Label der leeren Option
])

{{--
    <x-locale-select> — Dropdown der auswählbaren Sprachen aus der zentralen
    Registry (App\Support\Locales::enabled()). Wird in Organisations-, Profil-
    und Installer-Einstellung verwendet, damit die Sprachauswahl überall
    identisch und valide ist.

        <x-locale-select name="locale" :selected="$organization?->locale" />
        <x-locale-select name="preferences[locale]" :selected="$prefs['locale'] ?? null"
                         include-blank :blank-label="__('Organisation übernehmen')" />
--}}

@php
    $locales = \App\Support\Locales::enabled();
@endphp

<select name="{{ $name }}" {{ $attributes->merge(['class' => 'select select-bordered w-full']) }}>
    @if ($includeBlank)
        <option value="" @selected($selected === null || $selected === '')>{{ $blankLabel ?? __('— Standard —') }}</option>
    @endif
    @foreach ($locales as $code => $meta)
        <option value="{{ $code }}" @selected($selected === $code)>{{ $meta['flag'] }} {{ $meta['native'] }} ({{ $code }})</option>
    @endforeach
</select>
