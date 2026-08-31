{{--
  Link auf eine nutzer- oder fremdgesetzte URL (Sicherheitsscan 2026-08-23,
  S-47).

  Blade escaped die Attributzeichen, aber nicht das SCHEMA: `javascript:…` in
  einem href bleibt ein ausführbarer Link. Die Nonce-CSP blockiert die
  Ausführung heute — der Schutz darf aber nicht allein daran hängen, sonst
  wird jede spätere CSP-Lockerung zur Lücke.

  Erlaubt sind http(s), mailto, tel und relative Pfade. Alles andere wird als
  Klartext ausgegeben statt verschwiegen: eine Adresse, die jemand eingetragen
  hat, soll sichtbar bleiben — nur eben nicht anklickbar.

  @param string|null $url    Die Zieladresse
  @param string|null $label  Anzeigetext (Vorgabe: die Adresse selbst)
--}}
@props(['url' => null, 'label' => null])

@php
    $target = trim((string) $url);
    $text = $label !== null && $label !== '' ? $label : $target;
    $safe = $target !== '' && preg_match('#^(https?://|mailto:|tel:|/|\#|\?)#i', $target) === 1;
@endphp

@if ($target === '')
    {{-- nichts --}}
@elseif ($safe)
    <a {{ $attributes->merge(['class' => 'link', 'target' => '_blank', 'rel' => 'noopener noreferrer']) }}
       href="{{ $target }}">{{ $text }}</a>
@else
    <span {{ $attributes->except(['class', 'target', 'rel'])->merge(['class' => 'text-base-content/70']) }}
          title="{{ __('Kein aufrufbarer Link.') }}">{{ $text }}</span>
@endif
