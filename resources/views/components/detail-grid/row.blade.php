{{--
  Created on   : Fri May 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : row.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'label' => null,
    'value' => null,
])

@php
    $hasSlot = trim($slot) !== '';
@endphp

@if ($hasSlot || ($value !== null && $value !== ''))
    <dt class="text-base-content/60">{{ $label }}</dt>
    <dd {{ $attributes }}>{{ $hasSlot ? $slot : $value }}</dd>
@endif
