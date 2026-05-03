@props([
    'size'    => 'sm',   {{-- '', 'xs', 'sm' --}}
    'zebra'   => true,
    'pinRows' => false,
    'scroll'  => 'x',   {{-- 'x' | 'flex' --}}
])

@php
    $tableClasses = collect([
        'table',
        $size ? "table-{$size}" : null,
        $zebra ? 'table-zebra' : null,
        $pinRows ? 'table-pin-rows' : null,
    ])->filter()->implode(' ');

    $wrapperBase = $scroll === 'flex'
        ? 'min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100'
        : 'overflow-x-auto rounded-box border border-base-300 bg-base-100 shadow-sm';
@endphp

<div {{ $attributes->class([$wrapperBase]) }}>
    @if ($scroll === 'flex')
        <div class="h-full overflow-auto">
            <table class="{{ $tableClasses }}">
                {{ $slot }}
            </table>
        </div>
    @else
        <table class="{{ $tableClasses }}">
            {{ $slot }}
        </table>
    @endif
</div>
