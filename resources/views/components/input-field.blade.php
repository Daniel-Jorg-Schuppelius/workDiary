{{--
  Created on   : Fri May 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : input-field.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'span' => null,
    'error' => null,
    'hint' => null,
])

@php
    $errorKey      = $error ?? $name;
    $hasError      = $errorKey && $errors->has($errorKey);
    $isFile        = $type === 'file';
    $controlBase   = $isFile ? 'file-input file-input-bordered' : 'input input-bordered';
    $controlError  = $hasError ? ($isFile ? 'file-input-error' : 'input-error') : null;
    $wrapperClass  = 'fieldset' . ($span ? ' md:col-span-' . (int) $span : '');

    // Barrierefreiheit: Hilfetext und Fehlermeldung werden per aria-describedby
    // programmatisch mit dem Feld verknüpft (nicht nur visuell darunter).
    $hintId        = $hint ? $name . '-hint' : null;
    $errorId       = $hasError ? $name . '-error' : null;
    $describedBy   = implode(' ', array_filter([$hintId, $errorId])) ?: null;
@endphp

<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="fieldset-label" for="{{ $name }}">{{ $label }}@if ($required) *@endif</label>
    @endif

    @if (trim($slot) !== '')
        {{ $slot }}
    @else
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $name }}"
            @if ($required) required aria-required="true" @endif
            @if ($hasError) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @unless ($isFile) value="{{ $value }}" @endunless
            {{ $attributes->class(array_filter([$controlBase, 'w-full', $controlError])) }}
        >
    @endif

    @if ($hint)
        <p id="{{ $hintId }}" class="text-xs text-base-content/60 mt-1">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p id="{{ $errorId }}" class="text-error text-sm">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
