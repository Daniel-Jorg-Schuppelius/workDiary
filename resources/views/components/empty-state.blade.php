@props([
    'icon'    => null,
    'title'   => null,
    'message' => null,
    'tone'    => 'ghost',
    'compact' => false,
])

{{--
    <x-empty-state> — Standard-Anzeige für „keine Daten".

    Vereinheitlichter Default (tone="ghost"): graues Feld (bg-base-200) im
    umgebenden weißen Karten-Container. Andere `tone`-Werte (primary, success,
    warning, error, info) behalten die akzentuierten Varianten.

    Props:
      - icon    : optional, HTML/SVG-Icon-Markup
      - title   : optionale Überschrift
      - message : optionaler Beschreibungstext
      - tone    : ghost (Default, grau) | primary | success | warning | error | info
      - compact : kleinere Variante (geringeres Padding), z. B. innerhalb Tabellenzellen
--}}

@php
    $icon = $icon ?? '<span class="material-symbols-outlined" aria-hidden="true">inbox</span>';
    $title = $title ?? __('Keine Einträge vorhanden');
    $message = $message ?? __('Für die aktuelle Auswahl wurden keine Daten gefunden.');

    $toneClass = [
        'primary' => 'bg-primary/5 text-primary',
        'success' => 'bg-success/5 text-success',
        'warning' => 'bg-warning/5 text-warning',
        'error'   => 'bg-error/5 text-error',
        'info'    => 'bg-info/5 text-info',
        'ghost'   => 'bg-base-200 text-base-content/70',
    ][$tone] ?? 'bg-base-200 text-base-content/70';

    $padding = $compact ? 'px-4 py-6' : 'px-6 py-10';
@endphp

<div {{ $attributes->class([
    "wd-empty-state flex flex-col items-center justify-center gap-2 rounded-box text-center",
    $padding,
    $toneClass,
]) }}>
    @if ($icon)
        <div class="text-3xl opacity-70">
            {!! $icon !!}
        </div>
    @endif

    @if ($title)
        <h3 class="font-['Space_Grotesk'] text-base font-semibold text-base-content">{{ $title }}</h3>
    @endif

    @if ($message)
        <p class="max-w-prose text-sm text-base-content/70">{{ $message }}</p>
    @endif

    @isset($action)
        <div class="mt-2">
            {{ $action }}
        </div>
    @endisset

    {{ $slot }}
</div>
