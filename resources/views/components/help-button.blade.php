{{--
  Created on   : Sun May 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : help-button.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'topic',
    'label' => null,
    'size' => 'sm',
])

@php
    $resolvedLabel = $label ?? __('Hilfe');
    $sizeClass = match ($size) {
        'xs' => 'btn-xs',
        'md' => 'btn-md',
        default => 'btn-sm',
    };
@endphp

<button type="button"
        class="btn btn-ghost {{ $sizeClass }} text-base-content/70"
        data-help-trigger
        data-help-topic="{{ $topic }}"
        title="{{ $resolvedLabel }}"
        aria-label="{{ $resolvedLabel }}">
    <x-icon name="help" />
    <span class="sr-only">{{ $resolvedLabel }}</span>
</button>
