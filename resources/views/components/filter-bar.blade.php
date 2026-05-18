@props([
    'action'      => null,
    'method'      => 'GET',
    'reset'       => null,
    'submitLabel' => null,
])

@php
    $methodUpper = strtoupper($method);
    $isGet = $methodUpper === 'GET';
@endphp

<form
    method="{{ $isGet ? 'GET' : 'POST' }}"
    @if ($action) action="{{ $action }}" @endif
    {{ $attributes->class(['flex-none rounded-box border border-base-300 bg-base-100 p-4 shadow-xs']) }}
>
    @if (! $isGet)
        @csrf
        @if (! in_array($methodUpper, ['POST'], true))
            @method($methodUpper)
        @endif
    @endif
    <div class="flex flex-wrap items-end gap-2">
        {{ $slot }}

        <div class="ml-auto flex items-end gap-2">
            @isset($extra)
                {{ $extra }}
            @endisset
            <x-icon-btn icon="filter_alt" tone="primary" size="sm" type="submit"
                        show-label>{{ $submitLabel ?? __('Filtern') }}</x-icon-btn>
            @if ($reset)
                <x-icon-btn icon="restart_alt" size="sm"
                            :href="$reset"
                            show-label>{{ __('Zurücksetzen') }}</x-icon-btn>
            @endif
        </div>
    </div>
</form>
