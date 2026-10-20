{{--
  Created on   : Tue Oct 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : brand-logo.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'alt' => null,
])

{{--
    <x-brand-logo> — Logo der Organisation für helles und dunkles Farbschema
    (Phase 154, Nachtrag). Hat die Organisation ein eigenes dunkles Logo
    (Branding → „Logo dunkel“), rendert die Komponente beide Varianten; das
    Theme-Skript setzt html[data-scheme], das CSS (app.css, .wd-logo-*) blendet
    die passende ein — ohne Flackern, ohne Skript. Ohne dunkles Logo ein <img>.
    Klassen (Höhe, Breite) kommen vom Aufrufer.
--}}
@php
    $branding = app(\App\Services\UI\BrandingService::class);
    $light = $branding->logoUrl('light');
    $dark = $branding->logoUrl('dark');
    $name = $alt ?? $branding->appName();
    $switches = $light !== null && $dark !== null && $dark !== $light;
@endphp
@if ($light !== null)
    <img src="{{ $light }}" alt="{{ $name }}" {{ $attributes->class(['object-contain', 'wd-logo-light' => $switches]) }}>
    @if ($switches)
        <img src="{{ $dark }}" alt="{{ $name }}" {{ $attributes->class(['object-contain wd-logo-dark']) }}>
    @endif
@endif
