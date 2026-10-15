{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : form-group-collapsible.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Aufklappbare Formulargruppe (Feature 165, MVP-1193, E4): dieselben Felder
  wie <x-form-group>, nur zugeklappt, solange nichts dagegen spricht. Offen
  ist sie bei `open`, bei Pflichtgruppe (`required`), bei Validierungsfehlern
  in `fields` und wenn `values` gefüllte Werte enthält (Bearbeiten). Der
  Inhalt bleibt im DOM und wird immer mitgesendet — Zuklappen löscht nichts.
  Natives <details>, also tastatur- und screenreader-tauglich.
--}}
@props([
    'legend',
    'icon' => null,
    'tone' => 'ghost',
    'cols' => 1,
    'description' => null,
    'open' => false,
    'required' => false,
    'fields' => [],
    'values' => [],
])

@php
    $toneClass = in_array($tone, ['primary', 'secondary', 'accent', 'success', 'warning', 'error', 'info', 'ghost'], true)
        ? 'wd-fieldset--' . $tone
        : 'wd-fieldset--ghost';
    $cols = (int) $cols;
    $cols = in_array($cols, [1, 2, 3], true) ? $cols : 1;
    $colsClass = 'wd-fieldset__grid--cols-' . $cols;
    // Nur Material-Symbole (kein rohes Icon-Markup wie bei x-form-group).
    $iconIsSymbol = is_string($icon) && $icon !== '' && preg_match('/^[a-z0-9_]+$/', $icon) === 1;

    $filled = 0;
    foreach ((array) $values as $value) {
        if ($value === null || $value === '' || $value === false || $value === []) {
            continue;
        }
        $filled++;
    }
    $hasError = $fields !== [] && $errors->hasAny((array) $fields);
    $isOpen = (bool) $open || (bool) $required || $filled > 0 || $hasError;
@endphp

<details {{ $attributes->merge(['class' => 'wd-fieldset wd-fieldset--collapsible ' . $toneClass]) }}
         data-form-group
         @if ($hasError) data-form-group-error @endif
         @if ($isOpen) open @endif>
    <summary class="wd-fieldset__legend cursor-pointer list-none marker:content-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
        @if ($iconIsSymbol)
            <span class="wd-fieldset__legend-icon" aria-hidden="true">
                <x-icon :name="$icon" />
            </span>
        @endif
        <span>{{ $legend }}</span>
        @if ($filled > 0)
            <span class="badge badge-ghost badge-xs normal-case tracking-normal">{{ trans_choice('fields.collapsible.filled', $filled, ['count' => $filled]) }}</span>
        @endif
        @if ($hasError)
            <span class="badge badge-error badge-xs normal-case tracking-normal">{{ __('fields.collapsible.has_errors') }}</span>
        @endif
        <x-icon name="expand_more" class="wd-fieldset__legend-icon transition-transform group-open:rotate-180" />
    </summary>

    @if ($description)
        <p class="wd-fieldset__description">{{ $description }}</p>
    @endif

    <div class="wd-fieldset__grid {{ $colsClass }}">
        {{ $slot }}
    </div>
</details>
