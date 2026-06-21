@props([
    'tone'    => 'primary',   // primary | secondary | success | warning | info | error | ghost | outline | neutral
    'size'    => 'sm',        // xs | sm | md | lg
    'href'    => null,        // wenn gesetzt → <a>, sonst <button>
    'type'    => 'button',    // button | submit | reset
    'icon'    => null,        // optionales führendes Material-Symbol
    'iconTrailing' => null,   // optionales nachgestelltes Material-Symbol
    'iconFilled' => false,
    'block'   => false,       // volle Breite (btn-block)
    'loading' => false,       // statischer Lade-Spinner (z. B. Server-gerendert)
    'disabled' => false,
])

{{--
    <x-button> — kanonischer Text-Button für den neuen Bereich.

    Pendant zu <x-icon-btn> (Icon-only), aber text-first: vereinheitlicht die
    vielen rohen `<button class="btn btn-*">`/`<a class="btn …">` in Toolbars,
    Formularen und Dialogen. Gleiche tone/size-Konventionen wie x-icon-btn.

    Beispiele:
        <x-button>{{ __('Speichern') }}</x-button>
        <x-button tone="ghost" :href="route('foo.index')">{{ __('Abbrechen') }}</x-button>
        <x-button tone="error" type="submit" icon="delete">{{ __('Löschen') }}</x-button>
        <x-button type="submit" :loading="$saving">{{ __('Übernehmen') }}</x-button>
--}}

@php
    $sizeClass = match ($size) {
        'xs' => 'btn-xs',
        'md' => '',
        'lg' => 'btn-lg',
        default => 'btn-sm',
    };

    $toneClass = match ($tone) {
        'secondary' => 'btn-secondary',
        'success'   => 'btn-success',
        'warning'   => 'btn-warning',
        'info'      => 'btn-info',
        'error'     => 'btn-error',
        'ghost'     => 'btn-ghost',
        'outline'   => 'btn-outline',
        'neutral'   => 'btn-neutral',
        default     => 'btn-primary',
    };

    $btnClasses = collect(['btn', $sizeClass, $toneClass, 'gap-1', $block ? 'btn-block' : null])
        ->filter()
        ->implode(' ');

    $isDisabled = $disabled || $loading;
@endphp

@if ($href && ! $isDisabled)
    <a href="{{ $href }}" {{ $attributes->class([$btnClasses]) }}>
        @if ($loading)
            <span class="loading loading-spinner loading-xs" aria-hidden="true"></span>
        @elseif ($icon)
            <x-icon :name="$icon" :filled="$iconFilled" />
        @endif
        <span>{{ $slot }}</span>
        @if ($iconTrailing)
            <x-icon :name="$iconTrailing" :filled="$iconFilled" />
        @endif
    </a>
@else
    <button type="{{ $type }}"
            {{ $attributes->class([$btnClasses]) }}
            @if ($isDisabled) disabled aria-disabled="true" @endif>
        @if ($loading)
            <span class="loading loading-spinner loading-xs" aria-hidden="true"></span>
        @elseif ($icon)
            <x-icon :name="$icon" :filled="$iconFilled" />
        @endif
        <span>{{ $slot }}</span>
        @if ($iconTrailing)
            <x-icon :name="$iconTrailing" :filled="$iconFilled" />
        @endif
    </button>
@endif
