{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : icon.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'name',
    'filled' => false,
    'weight' => 400,
    'size' => null,
])

<span
    {{ $attributes->class(['material-symbols-outlined leading-none align-middle shrink-0 select-none']) }}
    style="font-variation-settings: 'FILL' {{ $filled ? 1 : 0 }}, 'wght' {{ (int) $weight }};{{ $size ? ' font-size: '.$size.';' : '' }}"
    aria-hidden="true"
    data-icon="{{ $name }}"
>{{ $name }}</span>
