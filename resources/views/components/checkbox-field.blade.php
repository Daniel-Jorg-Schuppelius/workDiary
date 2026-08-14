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
@endphp

<div class="{{ $wrapperClass }}">
    <label class="fieldset-label cursor-pointer gap-3">
        @if ($withHidden)
            <input type="hidden" name="{{ $name }}" value="0">
        @endif
        <input type="checkbox" name="{{ $name }}" value="{{ $value }}"
               {{ $attributes->class([$controlBase]) }}
               @checked($checked)>
        <span>{{ $label ?? $slot }}</span>
    </label>

    @if ($hint)
        <p class="text-xs text-base-content/60 mt-1">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p class="text-error text-sm">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
