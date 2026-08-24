{{--
  Created on   : Sun Jun 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : select-field.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'name',
    'id' => null,       // explizite id; nötig, wenn derselbe name mehrfach auf der Seite vorkommt (Loops/Detail-Formulare) — sonst doppelte ids (I13)
    'label' => null,
    'required' => false,
    'span' => null,
    'error' => null,
    'hint' => null,
])

@php
    $errorKey     = $error ?? $name;
    $hasError     = $errorKey && $errors->has($errorKey);
    $controlError = $hasError ? 'select-error' : null;
    $wrapperClass = 'fieldset' . ($span ? ' md:col-span-' . (int) $span : '');

    // Default bleibt name-basiert (stabile ids für Tests/Labels).
    $fieldId      = $id ?? $name;

    // Barrierefreiheit: Hilfetext/Fehler programmatisch mit dem Feld verknüpfen.
    $hintId       = $hint ? $fieldId . '-hint' : null;
    $errorId      = $hasError ? $fieldId . '-error' : null;
    $describedBy  = implode(' ', array_filter([$hintId, $errorId])) ?: null;
@endphp

<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="fieldset-label" for="{{ $fieldId }}">{{ $label }}@if ($required) *@endif</label>
    @endif

    {{-- Optionaler Einschub zwischen Label und Select (z. B. Suchfeld). --}}
    @isset($beforeSelect){{ $beforeSelect }}@endisset

    <select
        name="{{ $name }}"
        id="{{ $fieldId }}"
        @if ($required) required aria-required="true" @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class(array_filter(['select select-bordered', 'w-full', $controlError])) }}
    >{{ $slot }}</select>

    @if ($hint)
        <p id="{{ $hintId }}" class="text-xs text-muted mt-1">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p id="{{ $errorId }}" class="text-error text-sm">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
