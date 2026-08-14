{{--
  Created on   : Sat Jul 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : locale-flag.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Flaggen-SVG je Locale — inline (kein Asset/CDN), 3:2, Breite via Prop.
     Verwendet vom Sprachumschalter (ckonverter-Design: SVG statt Emoji,
     rendert plattformunabhängig identisch). --}}
@props([
    'code',
    'width' => 24,
])

@php
    $w = (int) $width;
    $h = (int) round($w * 2 / 3);
@endphp

@switch($code)
    @case('de')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2" width="{{ $w }}" height="{{ $h }}" {{ $attributes->class(['rounded-sm shrink-0']) }} aria-hidden="true">
            <rect width="3" height="2" fill="#FFCE00"/>
            <rect width="3" height="1.334" fill="#DD0000"/>
            <rect width="3" height="0.667" fill="#000"/>
        </svg>
        @break

    @case('en')
        {{-- Union Jack ist offiziell 2:1 — preserveAspectRatio="none" zieht ihn
             auf das einheitliche 3:2-Format der übrigen Flaggen. --}}
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 30" preserveAspectRatio="none" width="{{ $w }}" height="{{ $h }}" {{ $attributes->class(['rounded-sm shrink-0']) }} aria-hidden="true">
            <rect width="60" height="30" fill="#012169"/>
            <path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" stroke-width="6"/>
            <path d="M0,0 L60,30 M60,0 L0,30" stroke="#C8102E" stroke-width="4"/>
            <path d="M30,0 v30 M0,15 h60" stroke="#fff" stroke-width="10"/>
            <path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6"/>
        </svg>
        @break

    @case('fr')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2" width="{{ $w }}" height="{{ $h }}" {{ $attributes->class(['rounded-sm shrink-0']) }} aria-hidden="true">
            <rect width="3" height="2" fill="#EF4135"/>
            <rect width="2" height="2" fill="#fff"/>
            <rect width="1" height="2" fill="#0055A4"/>
        </svg>
        @break

    @case('it')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2" width="{{ $w }}" height="{{ $h }}" {{ $attributes->class(['rounded-sm shrink-0']) }} aria-hidden="true">
            <rect width="3" height="2" fill="#CE2B37"/>
            <rect width="2" height="2" fill="#fff"/>
            <rect width="1" height="2" fill="#009246"/>
        </svg>
        @break

    @case('es')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2" width="{{ $w }}" height="{{ $h }}" {{ $attributes->class(['rounded-sm shrink-0']) }} aria-hidden="true">
            <rect width="3" height="2" fill="#AA151B"/>
            <rect y="0.5" width="3" height="1" fill="#F1BF00"/>
        </svg>
        @break

    @default
        <span {{ $attributes->class(['text-xs font-bold uppercase']) }}>{{ $code }}</span>
@endswitch
