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
