{{--
  Created on   : Thu May 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dialog-status-controls.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    <x-dialog-status-controls>
    Kompakte Header-Steuerelemente für „Aktiv"-Toggle und „Farbe"-Picker.

    Wird in den `headerActions`-Slot von <x-modal> gerendert (oben rechts im Dialog-Header).
    Nutzt native HTML-Inputs (toggle + color), ohne sichtbare Labels — Bedeutung via aria-label/title.

    Props:
      - name        : Feldname des Active-Toggles (z. B. `is_active` oder `active`); null = kein Toggle
      - active      : Boolean-Default für den Toggle
      - color       : Hex-String für den Color-Picker; null = kein Color-Picker
      - colorName   : Feldname des Color-Inputs (default: `color`)
      - activeLabel : Tooltip/aria-label für den Toggle (default: __('Aktiv'))
      - colorLabel  : Tooltip/aria-label für den Color-Picker (default: __('Farbe'))
      - tone        : daisyUI-Tone für den Toggle (default: `primary`)
--}}
@props([
    'name'        => 'is_active',
    'active'      => true,
    'color'       => null,
    'colorName'   => 'color',
    'activeLabel' => null,
    'colorLabel'  => null,
    'tone'        => 'primary',
])

@php
    $activeLbl = $activeLabel ?? __('Aktiv');
    $colorLbl  = $colorLabel  ?? __('Farbe');
    $toggleCls = 'toggle toggle-sm toggle-' . $tone;
    $hasToggle = $name !== null && $name !== '';
    $hasColor  = $color !== null;
@endphp

@if ($hasToggle)
    <label class="inline-flex items-center cursor-pointer" title="{{ $activeLbl }}">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" name="{{ $name }}" value="1"
               class="{{ $toggleCls }}"
               aria-label="{{ $activeLbl }}"
               data-dialog-active-toggle
               @checked(old($name, $active))>
    </label>
@endif

@if ($hasColor)
    <input type="color" name="{{ $colorName }}"
           value="{{ old($colorName, $color) }}"
           class="wd-color-swatch"
           aria-label="{{ $colorLbl }}"
           title="{{ $colorLbl }}">
@endif
