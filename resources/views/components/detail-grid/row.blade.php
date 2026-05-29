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
