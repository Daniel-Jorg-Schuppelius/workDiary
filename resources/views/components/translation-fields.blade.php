{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : translation-fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Übersetzungen eines Stammdatentexts (MVP-912): je aktivierter Sprache außer Deutsch ein Feld `name[locale]`. --}}
@props(['name', 'values' => null, 'maxlength' => 180])
@php
    $values = is_array($values) ? $values : [];
    $locales = array_values(array_filter(\App\Support\Locales::enabledCodes(), static fn (string $l): bool => $l !== 'de'));
@endphp
@if ($locales !== [])
    <details class="rounded-box border border-base-300 px-3 py-2" @if (array_filter($values) !== []) open @endif>
        <summary class="cursor-pointer text-sm">{{ __('fields.translations.title') }}</summary>
        <p class="mt-1 text-xs text-muted">{{ __('fields.translations.hint') }}</p>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            @foreach ($locales as $locale)
                <div class="fieldset">
                    <label for="{{ $name }}-{{ $locale }}" class="fieldset-label">{{ \App\Support\Locales::native($locale) }}</label>
                    <input id="{{ $name }}-{{ $locale }}" type="text" name="{{ $name }}[{{ $locale }}]" value="{{ old($name . '.' . $locale, $values[$locale] ?? '') }}" maxlength="{{ $maxlength }}" class="input input-sm input-bordered w-full">
                </div>
            @endforeach
        </div>
    </details>
@endif
