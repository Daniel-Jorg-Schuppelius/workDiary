@props([
    'fromName' => 'from',
    'toName' => 'to',
    'from' => null,
    'to' => null,
    'type' => 'date',           // date | datetime-local | time
    'layout' => 'join',         // join | split
    'label' => null,            // overall label (join layout)
    'fromLabel' => null,        // input label (split) / aria + title (join)
    'toLabel' => null,
    'fromId' => null,
    'toId' => null,
    'size' => 'sm',             // sm | md | lg | '' to disable
    'required' => false,
    'fromError' => null,
    'toError' => null,
    'min' => null,
    'max' => null,
    'gridClass' => 'grid gap-3 sm:grid-cols-2',
    'labelClass' => null,       // override label class in split layout
    'inputClass' => '',         // extra classes appended to inputs
    'formControl' => false,     // wrap split fields with DaisyUI form-control
])

@php
    $fromLabel ??= __('Von');
    $toLabel ??= __('Bis');
    $label ??= $fromLabel.' – '.$toLabel;
    $sizeClass = $size ? 'input-'.$size : '';
    $inputBase = trim('input input-bordered '.$sizeClass);
    $splitLabelClass = $labelClass ?? ($formControl ? 'label' : 'label text-sm font-semibold pb-1');
@endphp

@if ($layout === 'split')
    <div {{ $attributes->merge(['class' => $gridClass]) }}>
        @if ($formControl)
            <label class="form-control w-full">
                <div class="label"><span class="label-text">{{ $fromLabel }}</span></div>
                <input
                    @if($fromId) id="{{ $fromId }}" @endif
                    name="{{ $fromName }}"
                    type="{{ $type }}"
                    value="{{ $from }}"
                    @if($required) required @endif
                    @if($min !== null) min="{{ $min }}" @endif
                    @if($max !== null) max="{{ $max }}" @endif
                    class="{{ $inputBase }} w-full {{ $inputClass }} @if($fromError) input-error @endif"
                >
                @if($fromError)<p class="mt-1 text-sm text-error">{{ $fromError }}</p>@endif
            </label>
            <label class="form-control w-full">
                <div class="label"><span class="label-text">{{ $toLabel }}</span></div>
                <input
                    @if($toId) id="{{ $toId }}" @endif
                    name="{{ $toName }}"
                    type="{{ $type }}"
                    value="{{ $to }}"
                    @if($required) required @endif
                    @if($min !== null) min="{{ $min }}" @endif
                    @if($max !== null) max="{{ $max }}" @endif
                    class="{{ $inputBase }} w-full {{ $inputClass }} @if($toError) input-error @endif"
                >
                @if($toError)<p class="mt-1 text-sm text-error">{{ $toError }}</p>@endif
            </label>
        @else
            <div>
                <label @if($fromId) for="{{ $fromId }}" @endif class="{{ $splitLabelClass }}">{{ $fromLabel }}</label>
                <input
                    @if($fromId) id="{{ $fromId }}" @endif
                    name="{{ $fromName }}"
                    type="{{ $type }}"
                    value="{{ $from }}"
                    @if($required) required @endif
                    @if($min !== null) min="{{ $min }}" @endif
                    @if($max !== null) max="{{ $max }}" @endif
                    class="{{ $inputBase }} w-full {{ $inputClass }} @if($fromError) input-error @endif"
                >
                @if($fromError)<p class="mt-1 text-sm text-error">{{ $fromError }}</p>@endif
            </div>
            <div>
                <label @if($toId) for="{{ $toId }}" @endif class="{{ $splitLabelClass }}">{{ $toLabel }}</label>
                <input
                    @if($toId) id="{{ $toId }}" @endif
                    name="{{ $toName }}"
                    type="{{ $type }}"
                    value="{{ $to }}"
                    @if($required) required @endif
                    @if($min !== null) min="{{ $min }}" @endif
                    @if($max !== null) max="{{ $max }}" @endif
                    class="{{ $inputBase }} w-full {{ $inputClass }} @if($toError) input-error @endif"
                >
                @if($toError)<p class="mt-1 text-sm text-error">{{ $toError }}</p>@endif
            </div>
        @endif
    </div>
@else
    <div {{ $attributes->merge(['class' => 'flex flex-col']) }}>
        @if ($label !== false && $label !== '')
            <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ $label }}</span></label>
        @endif
        <div class="join">
            <input
                type="{{ $type }}"
                name="{{ $fromName }}"
                value="{{ $from }}"
                @if($required) required @endif
                @if($min !== null) min="{{ $min }}" @endif
                @if($max !== null) max="{{ $max }}" @endif
                class="join-item {{ $inputBase }} {{ $inputClass }}"
                title="{{ $fromLabel }}"
                aria-label="{{ $fromLabel }}"
            >
            <input
                type="{{ $type }}"
                name="{{ $toName }}"
                value="{{ $to }}"
                @if($required) required @endif
                @if($min !== null) min="{{ $min }}" @endif
                @if($max !== null) max="{{ $max }}" @endif
                class="join-item {{ $inputBase }} {{ $inputClass }}"
                title="{{ $toLabel }}"
                aria-label="{{ $toLabel }}"
            >
        </div>
    </div>
@endif
