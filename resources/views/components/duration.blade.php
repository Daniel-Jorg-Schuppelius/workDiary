{{-- mode: clock | decimal | both --}}
@props([
    'minutes' => 0,
    'mode' => 'both',
    'withUnit' => true,
])
<span {{ $attributes->merge(['class' => 'whitespace-nowrap tabular-nums']) }}>{{ \App\Support\Formats::duration((int) $minutes, $mode, (bool) $withUnit) }}</span>
