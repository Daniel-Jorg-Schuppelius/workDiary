{{--
  Begriffs-Tooltip (Feature 039): umschließt einen Fachbegriff mit einem
  daisyUI-Tooltip aus dem Glossar (lang/<locale>/glossary.php). Verwendung:

    <x-term glossary="nacharbeit">{{ __('Nacharbeit') }}</x-term>
    <x-term :tip="__('Freitext-Erklärung')">Begriff</x-term>

  Ohne auflösbaren Text (unbekannter Glossar-Key) wird der Inhalt unverändert
  gerendert — nie ein „glossary.x"-Rohkey als Tooltip.
--}}
@props([
    'glossary' => null,
    'tip' => null,
])

@php
    $text = $tip;
    if ($text === null && $glossary !== null) {
        $key = 'glossary.' . $glossary;
        $resolved = __($key);
        $text = $resolved === $key ? null : $resolved;
    }
@endphp

@if ($text)
    <span {{ $attributes->merge(['class' => 'tooltip tooltip-bottom cursor-help underline decoration-dotted decoration-base-content/40 underline-offset-2']) }}
          data-tip="{{ $text }}">{{ $slot }}</span>
@else
    {{ $slot }}
@endif
