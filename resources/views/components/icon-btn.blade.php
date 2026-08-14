{{--
  Created on   : Mon May 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : icon-btn.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'icon'    => null,        // Material-Symbol-Name (z. B. "edit", "delete", "visibility", "add")
    'label'   => null,        // Tooltip + aria-label; auch als sichtbarer Text, wenn $slot leer
    'showLabel' => false,     // sichtbar neben Icon anzeigen (Default: nur Icon)
    'tone'    => 'ghost',     // ghost | primary | secondary | error | success | warning | info | outline
    'size'    => 'xs',        // xs | sm | md | lg
    'href'    => null,        // wenn gesetzt → <a>, sonst <button>
    'type'    => 'button',    // button | submit | reset
    'iconFilled' => false,
])

{{--
    <x-icon-btn> — kanonischer Icon-Button für den neuen Bereich.

    Vereinheitlicht das Markup für Symbol-Buttons in Toolbars, Filter-Bars und
    Tabellenzeilen. Nutzt intern <x-icon> für das Material-Symbol; setzt
    title + aria-label aus `label` für Tooltip und Screenreader.

    Beispiele:
        <x-icon-btn icon="edit"   label="{{ __('Bearbeiten') }}" :href="route('foo.edit', $foo)" />
        <x-icon-btn icon="delete" label="{{ __('Löschen') }}"   type="submit" tone="error" />
        <x-icon-btn icon="add"    label="{{ __('Material') }}"  tone="primary" size="sm" show-label />
--}}

@php
    $sizeClass = match ($size) {
        'sm' => 'btn-sm',
        'md' => '',
        'lg' => 'btn-lg',
        default => 'btn-xs',
    };

    $toneClass = match ($tone) {
        'primary'   => 'btn-primary',
        'secondary' => 'btn-secondary',
        'success'   => 'btn-success',
        'warning'   => 'btn-warning',
        'info'      => 'btn-info',
        'error'     => 'btn-ghost text-error',
        'outline'   => 'btn-outline',
        default     => 'btn-ghost',
    };

    $hasSlot = trim($slot ?? '') !== '';
    $showText = $showLabel || $hasSlot;
    $btnClasses = collect(['btn', $sizeClass, $toneClass, $showText ? 'gap-1' : null])
        ->filter()
        ->implode(' ');

    // Barrierefreiheit: Ein reiner Icon-Button (kein sichtbarer Text) MUSS einen
    // zugänglichen Namen tragen. Bevorzugt `label`; fehlt er im Icon-only-Fall,
    // fällt der Name auf den Icon-Namen zurück, damit nie ein komplett
    // unbeschrifteter Button entsteht. Zeigt der Button sichtbaren Text, ist
    // dieser bereits der Name → kein zusätzliches aria-label nötig.
    $a11yLabel = $label ?: (! $showText ? $icon : null);
@endphp

@if ($href)
    <a href="{{ $href }}"
       {{ $attributes->class([$btnClasses]) }}
       @if ($a11yLabel) title="{{ $a11yLabel }}" aria-label="{{ $a11yLabel }}" @endif>
        @if ($icon)
            <x-icon :name="$icon" :filled="$iconFilled" />
        @endif
        @if ($showText)
            <span>{{ $hasSlot ? $slot : $label }}</span>
        @endif
    </a>
@else
    <button type="{{ $type }}"
            {{ $attributes->class([$btnClasses]) }}
            @if ($a11yLabel) title="{{ $a11yLabel }}" aria-label="{{ $a11yLabel }}" @endif>
        @if ($icon)
            <x-icon :name="$icon" :filled="$iconFilled" />
        @endif
        @if ($showText)
            <span>{{ $hasSlot ? $slot : $label }}</span>
        @endif
    </button>
@endif
