@props([
    'name',
    'label',
    'id'      => null,
    'checked' => false,
    'value'   => 1,
    'title'   => null,
    'tone'    => 'primary',   // DaisyUI-Farbe des Schalters (primary|error|…)
])

{{--
    <x-filter-toggle> — Ja/Nein-Schalter in einer <x-filter-bar>.

    `order-40` sortiert alle Schalter ans Ende der Leiste, unabhängig davon, wo
    sie im Quelltext stehen: Der Standard-Filtersatz kommt als @include vor den
    seitenspezifischen Feldern, sein Schalter würde sonst mitten zwischen den
    Auswahlfeldern landen. Der Aktionsblock der Leiste liegt auf `order-50`.
--}}

@php($fieldId = $id ?? 'toggle-' . $name)

<label class="order-40 flex shrink-0 cursor-pointer items-center gap-2" for="{{ $fieldId }}"
       @if ($title) title="{{ $title }}" @endif>
    <input type="checkbox" id="{{ $fieldId }}" name="{{ $name }}" value="{{ $value }}"
           @checked($checked)
           {{ $attributes->class(['toggle toggle-sm', 'toggle-' . $tone]) }}>
    <span class="whitespace-nowrap text-sm text-base-content/75">{{ $label }}</span>
</label>
