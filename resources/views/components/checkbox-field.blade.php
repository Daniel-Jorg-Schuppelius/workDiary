{{--
  Created on   : Sun Jun 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : checkbox-field.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'name',
    'id' => null,       // explizite id; nötig, wenn derselbe name mehrfach auf der Seite vorkommt (Loops/Detail-Formulare) — sonst doppelte ids (I13)
    'label' => null,
    'checked' => false,
    'value' => '1',
    'toggle' => true,
    'tone' => 'primary',
    'span' => null,
    'error' => null,
    'hint' => null,
    'withHidden' => true,
])

@php
    $errorKey     = $error ?? $name;
    $hasError     = $errorKey && $errors->has($errorKey);
    $wrapperClass = 'fieldset' . ($span ? ' md:col-span-' . (int) $span : '');
    $controlBase  = $toggle ? 'toggle toggle-' . $tone : 'checkbox checkbox-' . $tone;

    // Das Label umschließt die Checkbox (implizite Verknüpfung) — die id wird
    // nur gerendert, wenn explizit gesetzt (Loops wie name="…[]" kollidierten
    // sonst); Hint-/Fehler-ids bleiben name-basiert stabil.
    $fieldId      = $id ?? $name;

    // Barrierefreiheit: Hilfetext/Fehler programmatisch mit dem Feld verknüpfen.
    $hintId       = $hint ? $fieldId . '-hint' : null;
    $errorId      = $hasError ? $fieldId . '-error' : null;
    $describedBy  = implode(' ', array_filter([$hintId, $errorId])) ?: null;
@endphp

<div class="{{ $wrapperClass }}">
    <label class="fieldset-label cursor-pointer gap-3">
        @if ($withHidden)
            <input type="hidden" name="{{ $name }}" value="0">
        @endif
        <input type="checkbox" name="{{ $name }}" value="{{ $value }}"
               @if ($id !== null) id="{{ $id }}" @endif
               @if ($hasError) aria-invalid="true" @endif
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
               {{ $attributes->class([$controlBase]) }}
               @checked($checked)>
        <span>{{ $label ?? $slot }}</span>
    </label>

    @if ($hint)
        <p id="{{ $hintId }}" class="text-xs text-muted mt-1">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p id="{{ $errorId }}" class="text-error text-sm">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
