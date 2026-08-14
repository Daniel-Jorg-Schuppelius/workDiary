{{--
  Created on   : Sat Jun 06 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : timezone-select.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'name' => 'timezone',
    'selected' => null,        // aktuell gewählter IANA-Bezeichner
    'includeBlank' => false,   // true → leere Option ("Standard übernehmen")
    'blankLabel' => null,      // Label der leeren Option
])

{{--
    <x-timezone-select> — gruppiertes Dropdown aller IANA-Zeitzonen.

    Wird in der Organisations- und der Profil-Einstellung verwendet, damit die
    Zeitzonen-Auswahl überall identisch (und valide) ist.

        <x-timezone-select name="timezone" :selected="$organization?->timezone" />
        <x-timezone-select name="preferences[timezone]" :selected="$prefs['timezone'] ?? null"
                           include-blank :blank-label="__('Organisation übernehmen')" />
--}}

@php
    $groups = \App\Support\Tz::grouped();
@endphp

<select name="{{ $name }}" {{ $attributes->merge(['class' => 'select select-bordered w-full']) }}>
    @if ($includeBlank)
        <option value="" @selected($selected === null || $selected === '')>{{ $blankLabel ?? __('— Standard —') }}</option>
    @endif
    @foreach ($groups as $region => $identifiers)
        <optgroup label="{{ $region }}">
            @foreach ($identifiers as $identifier)
                <option value="{{ $identifier }}" @selected($selected === $identifier)>{{ str_replace('_', ' ', $identifier) }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
