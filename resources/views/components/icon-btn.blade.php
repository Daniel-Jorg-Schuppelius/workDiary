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
@endphp

@if ($href)
    <a href="{{ $href }}"
       {{ $attributes->class([$btnClasses]) }}
       @if ($label) title="{{ $label }}" aria-label="{{ $label }}" @endif>
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
            @if ($label) title="{{ $label }}" aria-label="{{ $label }}" @endif>
        @if ($icon)
            <x-icon :name="$icon" :filled="$iconFilled" />
        @endif
        @if ($showText)
            <span>{{ $hasSlot ? $slot : $label }}</span>
        @endif
    </button>
@endif
