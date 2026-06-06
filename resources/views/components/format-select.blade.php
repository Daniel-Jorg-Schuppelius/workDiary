@props([
    'type' => 'date',          // date | time
    'name' => null,
    'selected' => null,        // aktuell gewähltes PHP-Format
    'includeBlank' => false,   // leere Option (z. B. "Organisation übernehmen")
    'blankLabel' => null,
])

{{--
    <x-format-select> — Dropdown der kuratierten, flatpickr-kompatiblen Datums-
    bzw. Uhrzeitformate (App\Support\Formats). Jede Option zeigt das Format plus
    ein Live-Beispiel. Wird in Org- und Profil-Einstellung verwendet.

        <x-format-select type="date" name="settings[personalization][date_format]"
                         :selected="$org?->settings['personalization']['date_format'] ?? null" />
--}}

@php
    $options = $type === 'time'
        ? \App\Support\Formats::timeOptions()
        : \App\Support\Formats::dateOptions();
    $sample = \Illuminate\Support\Carbon::create(2026, 12, 31, 14, 5);
@endphp

<select name="{{ $name }}" {{ $attributes->merge(['class' => 'select select-bordered w-full']) }}>
    @if ($includeBlank)
        <option value="" @selected($selected === null || $selected === '')>{{ $blankLabel ?? __('— Standard —') }}</option>
    @endif
    @foreach ($options as $fmt)
        <option value="{{ $fmt }}" @selected($selected === $fmt)>{{ $sample->translatedFormat($fmt) }} ({{ $fmt }})</option>
    @endforeach
</select>
