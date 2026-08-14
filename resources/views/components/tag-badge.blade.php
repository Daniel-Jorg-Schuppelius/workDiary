{{--
  Created on   : Sun Jun 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : tag-badge.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'tag' => null,      // Tag-Modell mit ->name und ->color
    'color' => null,    // optionale Farb-Übersteuerung (Hex)
    'name' => null,     // optionaler Text-Übersteuerung
])

{{--
    <x-tag-badge> — farbiges Tag-/Kategorie-Badge.
    <x-tag-badge :tag="$tag" /> oder <x-tag-badge :name="$cat->name" :color="$cat->color" />
--}}

@php
    $bg    = $color ?? ($tag?->color ?? '#e5e7eb');
    $label = $name ?? $tag?->name;
@endphp

<span {{ $attributes->merge(['class' => 'badge badge-sm']) }} style="background:{{ $bg }};color:#000">{{ $label }}</span>
