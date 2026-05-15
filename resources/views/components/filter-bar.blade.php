@props([
    'action' => null,
    'method' => 'GET',
    'reset' => null,
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
    <div class="flex flex-wrap items-end gap-3">
        {{ $slot }}

        <div class="ml-auto flex items-end gap-2">
            @isset($extra)
                {{ $extra }}
            @endisset
            <button type="submit" class="btn btn-primary btn-sm gap-1">
                <x-icon name="filter_alt" />
                <span>{{ $submitLabel ?? __('Filtern') }}</span>
            </button>
            @if ($reset)
                <a href="{{ $reset }}" class="btn btn-ghost btn-sm gap-1">
                    <x-icon name="restart_alt" />
                    <span>{{ __('Zurücksetzen') }}</span>
                </a>
            @endif
        </div>
    </div>
</form>
